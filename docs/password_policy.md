# Password Policy Feature

The password policy feature provides server-side enforcement of password requirements, history tracking, and expiry management.

## Features

### ✅ Always Available (No Additional Setup Required)

These features work out-of-the-box when `PASSWORD_POLICY_ENABLED=true`:

- **Password Complexity Validation**
  - Minimum length enforcement
  - Uppercase letter requirements
  - Lowercase letter requirements
  - Number requirements
  - Special character requirements

- **Password Strength Scoring**
  - 0-4 score based on length and complexity
  - Configurable minimum score threshold

### ⚙️ Requires OpenLDAP ppolicy Overlay

These features require the OpenLDAP ppolicy (password policy) overlay to be configured:

- **Password History (Self-Service)**
  - Prevents users from reusing recent passwords when changing their own password
  - Configurable history count (last N passwords)
  - Uses OpenLDAP's ppolicy overlay for server-side enforcement
  - Requires `PPOLICY_ENABLED=true` and secure connection (STARTTLS or LDAPS)

- **Password History (Admin)**
  - Prevents reuse of recent passwords when admins change user passwords
  - Uses `pwdHistory` operational attribute (manual tracking)
  - Note: Admin password changes do not trigger ppolicy enforcement

- **Password Expiry**
  - Automatic password expiration after N days
  - Expiry warnings before password expires
  - Uses `pwdChangedTime` operational attribute

## Configuration

### Basic Setup (Complexity Only)

```bash
docker run -e PASSWORD_POLICY_ENABLED=true \
           -e PASSWORD_MIN_LENGTH=12 \
           -e PASSWORD_REQUIRE_UPPERCASE=true \
           -e PASSWORD_REQUIRE_LOWERCASE=true \
           -e PASSWORD_REQUIRE_NUMBERS=true \
           -e PASSWORD_REQUIRE_SPECIAL=true \
           -e PASSWORD_MIN_SCORE=3 \
           luminary
```

### Full Setup (History & Expiry)

**Prerequisites:**
- OpenLDAP ppolicy overlay must be loaded and configured
- STARTTLS or LDAPS must be enabled for self-service password changes

```bash
docker run -e PASSWORD_POLICY_ENABLED=true \
           -e PASSWORD_MIN_LENGTH=12 \
           -e PASSWORD_REQUIRE_UPPERCASE=true \
           -e PASSWORD_REQUIRE_LOWERCASE=true \
           -e PASSWORD_REQUIRE_NUMBERS=true \
           -e PASSWORD_REQUIRE_SPECIAL=true \
           -e PASSWORD_MIN_SCORE=3 \
           -e PPOLICY_ENABLED=true \
           -e PASSWORD_HISTORY_COUNT=5 \
           -e PASSWORD_EXPIRY_DAYS=90 \
           -e PASSWORD_EXPIRY_WARNING_DAYS=14 \
           -e LDAP_REQUIRE_STARTTLS=true \
           luminary
```

**Important:** `PPOLICY_ENABLED=true` enables server-side password policy enforcement for self-service password changes. This requires a secure LDAP connection (STARTTLS or LDAPS) because the user's cleartext password must be sent to OpenLDAP using the Password Modify Extended Operation (RFC 3062).

## Enabling OpenLDAP ppolicy Overlay

### Option 1: Using osixia/openldap Docker Image

```yaml
# docker-compose.yml
version: '3'
services:
  openldap:
    image: osixia/openldap:latest
    environment:
      - LDAP_ORGANISATION=Example Inc
      - LDAP_DOMAIN=example.com
      - LDAP_ADMIN_PASSWORD=admin
    command: --copy-service --loglevel debug
    volumes:
      - ./ppolicy-config.ldif:/container/service/slapd/assets/config/bootstrap/ldif/custom/ppolicy.ldif
```

**ppolicy-config.ldif:**
```ldif
# Load ppolicy module
dn: cn=module{0},cn=config
changetype: modify
add: olcModuleLoad
olcModuleLoad: ppolicy

# Add ppolicy overlay to database
dn: olcOverlay=ppolicy,olcDatabase={1}mdb,cn=config
objectClass: olcOverlayConfig
objectClass: olcPPolicyConfig
olcOverlay: ppolicy
olcPPolicyDefault: cn=default,ou=policies,dc=example,dc=com
olcPPolicyHashCleartext: TRUE
olcPPolicyUseLockout: TRUE

# Create default password policy
dn: ou=policies,dc=example,dc=com
objectClass: organizationalUnit
ou: policies

dn: cn=default,ou=policies,dc=example,dc=com
objectClass: pwdPolicy
objectClass: device
objectClass: top
cn: default
pwdAttribute: userPassword
pwdMaxAge: 7776000
pwdExpireWarning: 1209600
pwdInHistory: 5
pwdCheckQuality: 0
pwdMinLength: 8
pwdMaxFailure: 5
pwdLockout: TRUE
pwdLockoutDuration: 1800
pwdGraceAuthNLimit: 0
pwdFailureCountInterval: 0
pwdMustChange: FALSE
pwdAllowUserChange: TRUE
pwdSafeModify: FALSE
```

### Option 2: Manual Configuration (cn=config)

```bash
# 1. Load ppolicy module
ldapadd -Y EXTERNAL -H ldapi:/// <<EOF
dn: cn=module{0},cn=config
changetype: modify
add: olcModuleLoad
olcModuleLoad: ppolicy
EOF

# 2. Add ppolicy overlay
ldapadd -Y EXTERNAL -H ldapi:/// <<EOF
dn: olcOverlay=ppolicy,olcDatabase={1}mdb,cn=config
objectClass: olcOverlayConfig
objectClass: olcPPolicyConfig
olcOverlay: ppolicy
olcPPolicyHashCleartext: TRUE
EOF
```

## Testing ppolicy Availability

Luminary automatically detects if ppolicy overlay is available. To test manually:

```bash
ldapsearch -x -H ldap://localhost -b "" -s base "(objectClass=*)" supportedControl | grep 1.3.6.1.4.1.42.2.27.8.5.1
```

If ppolicy is available, you'll see the control OID: `1.3.6.1.4.1.42.2.27.8.5.1`

## How It Works

### Password Complexity Validation

When an admin creates or updates a user password, Luminary validates it against configured requirements:

1. Check minimum length
2. Check for uppercase letters (if required)
3. Check for lowercase letters (if required)
4. Check for numbers (if required)
5. Check for special characters (if required)
6. Calculate strength score (0-4)
7. Reject if score below minimum threshold

**Error messages** are shown to the admin with specific reasons for rejection.

### Password History (with ppolicy)

Password history enforcement works differently for admin and self-service password changes:

#### Self-Service Password Changes (PPOLICY_ENABLED=true)

When users change their own passwords through the "Change your password" page:

1. **Connection:**
   - Luminary connects to LDAP as the user (not admin)
   - STARTTLS or LDAPS is required for security

2. **Password change:**
   - Uses LDAP Password Modify Extended Operation (RFC 3062)
   - OpenLDAP's ppolicy overlay intercepts the request
   - ppolicy automatically checks password against history
   - ppolicy automatically updates `pwdHistory` and `pwdChangedTime`

3. **On password validation:**
   - If password found in history, ppolicy rejects with: "Password is in history of old passwords"
   - User sees: "Password was used recently and cannot be reused"

#### Admin Password Changes (Manual Tracking)

When admins change user passwords through the account manager:

1. **On password change:**
   - New password hash is added to `pwdHistory` attribute manually
   - History is trimmed to `PASSWORD_HISTORY_COUNT` entries
   - `pwdChangedTime` is updated to current timestamp
   - Note: ppolicy overlay does NOT enforce history for admin changes

2. **On password validation:**
   - New password is checked against all hashes in `pwdHistory`
   - If match found, password is rejected with message: "Password was used recently and cannot be reused"

### Password Expiry (with ppolicy)

When ppolicy overlay is available:

1. **On login:**
   - Check `pwdChangedTime` attribute
   - Calculate days since last change
   - If > `PASSWORD_EXPIRY_DAYS`, force password change
   - If within `PASSWORD_EXPIRY_WARNING_DAYS`, show warning

2. **Expiry calculation:**
   ```
   pwdChangedTime: 20251114120000Z (Nov 14, 2025)
   PASSWORD_EXPIRY_DAYS: 90
   Expires: Feb 12, 2026
   ```

## Security Considerations

### Password Storage

- **Current password:** Stored as `userPassword` with SSHA hash (salted SHA-1)
- **Historical passwords:** Stored as `pwdHistory` (multi-valued, SSHA hashes)
- **Timestamps:** Stored as `pwdChangedTime` (LDAP GeneralizedTime format)

### Access Control

Ensure your LDAP ACLs protect password attributes:

```ldif
# Recommended ACL
olcAccess: to attrs=userPassword
  by self write
  by anonymous auth
  by * none

olcAccess: to attrs=pwdHistory,pwdChangedTime
  by self read
  by * none
```

### Hash Algorithm

LDAP standard uses SSHA (Salted SHA-1):
- Each hash includes a random salt
- Format: `{SSHA}base64(sha1(password+salt) + salt)`
- Resistant to rainbow table attacks
- Not the strongest (consider SSHA256/SSHA512 if supported)

## Troubleshooting

### "Password history requires ppolicy overlay - feature disabled"

**Cause:** ppolicy overlay not loaded or not configured

**Solution:**
1. Check OpenLDAP logs for ppolicy errors
2. Verify ppolicy module is loaded: `ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config "(olcModuleLoad=ppolicy)"`
3. Verify ppolicy overlay exists: `ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config "(olcOverlay=ppolicy)"`
4. Restart OpenLDAP after configuration changes

### Password history not preventing reuse

**Possible causes:**
1. ppolicy overlay not active
2. `pwdHistory` attribute not being written (check permissions)
3. Hash algorithm mismatch (check `olcPPolicyHashCleartext`)

**Debug:**
```bash
# Check if pwdHistory is being written
ldapsearch -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=people,dc=example,dc=com" \
  "(uid=jdoe)" pwdHistory pwdChangedTime
```

### Complexity validation not working

**Possible causes:**
1. `PASSWORD_POLICY_ENABLED` not set to `true`
2. Boolean environment variable case mismatch (use `TRUE` not `true`)

**Check:**
```bash
docker exec luminary printenv | grep PASSWORD_POLICY
```

### "The password could not be changed due to a system configuration issue"

**Cause:** PPOLICY_ENABLED=true but TLS is not available

**Solution:**
1. Check that STARTTLS or LDAPS is working:
   ```bash
   docker logs luminary | grep "Start STARTTLS\|Using an LDAPS"
   ```
2. If using ldap:// (not ldaps://), ensure STARTTLS is working
3. Check Luminary logs for detailed error:
   ```bash
   docker logs luminary | grep "ppolicy password change"
   ```

**Common issues:**
- LDAP_REQUIRE_STARTTLS not set to TRUE
- LDAP server doesn't support StartTLS
- Certificate validation errors (set LDAP_IGNORE_CERT_ERRORS=true for testing)

### Self-service password change requires current password

**Cause:** This is expected behaviour when PPOLICY_ENABLED=true

**Explanation:** When ppolicy is enabled, Luminary must bind as the user (not admin) to trigger ppolicy enforcement. This requires the user's current password for authentication.

## References

- [OpenLDAP ppolicy overlay documentation](https://www.openldap.org/doc/admin24/overlays.html#Password%20Policies)
- [RFC 2307 - LDAP Network Services](https://www.rfc-editor.org/rfc/rfc2307)
- [ppolicy.schema](https://www.openldap.org/software/man.cgi?query=slapo-ppolicy)

## See Also

- [MFA Documentation](mfa.md) - Multi-factor authentication setup
- [Audit Logging Documentation](audit_logging.md) - Track password changes
- [Account Lifecycle Documentation](account_lifecycle.md) - Account expiration management
- [Configuration Reference](configuration.md) - All available settings
