# Account Lifecycle Feature

The account lifecycle feature provides account expiration management, account locking, and lifecycle tracking using standard LDAP schemas. No custom schema extensions are required.

## Features

- **Account Expiration** - Set expiration dates for temporary accounts (contractors, students, etc.)
- **Expiration Warnings** - Warn users before their account expires
- **Automatic Enforcement** - Block login when account is expired (enforced at login time)
- **Account Locking** - Lock/unlock accounts via ppolicy overlay (optional)
- **Self-Service Visibility** - Users see their own expiration date and warning
- **Admin Management** - Set, modify, or remove expiration dates via web UI
- **Standards-Based** - Uses RFC 2307 shadowAccount schema (no custom attributes)

## Standards & Schemas Used

### shadowAccount (RFC 2307)

The account lifecycle feature uses the standard `shadowAccount` object class from RFC 2307:

**Attributes:**
- `shadowExpire` - Account expiration date (days since Unix epoch: Jan 1, 1970)
- `shadowWarning` - Days before expiry to warn user (optional, not currently used by Luminary)

**Example:**
```ldif
dn: uid=jsmith,ou=people,dc=example,dc=com
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: shadowAccount
uid: jsmith
shadowExpire: 20454
# This equals: 20454 days * 86400 seconds = Jan 1, 2026
```

### ppolicy Overlay (Optional)

For account locking functionality, Luminary uses the OpenLDAP ppolicy overlay:

**Attributes:**
- `pwdAccountLockedTime` - Timestamp when account was locked

**Requires:** `PPOLICY_ENABLED=true` and ppolicy overlay configured on OpenLDAP server.

### Operational Attributes (Standard LDAP)

Luminary can display standard LDAP operational attributes for account lifecycle tracking:

- `createTimestamp` - When account was created (YYYYMMDDHHMMSSZ)
- `modifyTimestamp` - When account was last modified (YYYYMMDDHHMMSSZ)

## Configuration

### Basic Setup

```bash
docker run -e LIFECYCLE_ENABLED=true \
           -e ACCOUNT_EXPIRY_ENABLED=true \
           -e ACCOUNT_EXPIRY_WARNING_DAYS=14 \
           luminary
```

### With ppolicy (Account Locking)

```bash
docker run -e LIFECYCLE_ENABLED=true \
           -e ACCOUNT_EXPIRY_ENABLED=true \
           -e ACCOUNT_EXPIRY_WARNING_DAYS=14 \
           -e PPOLICY_ENABLED=true \
           luminary
```

**Note:** ppolicy overlay must be configured on OpenLDAP server. See [Password Policy Documentation](password_policy.md) for setup instructions.

### Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `LIFECYCLE_ENABLED` | `FALSE` | Enable account lifecycle features |
| `ACCOUNT_EXPIRY_ENABLED` | `FALSE` | Enable account expiration |
| `ACCOUNT_EXPIRY_WARNING_DAYS` | `14` | Days before expiry to show warning |
| `PPOLICY_ENABLED` | `FALSE` | Enable ppolicy overlay features (locking) |

## How It Works

### Account Expiration

#### 1. Admin Sets Expiration Date

**Via Web UI:**
- Admin → Account Manager → Click user → Edit Lifecycle tab
- Select expiration date from calendar
- Click "Set expiration"

**What happens:**
1. If user doesn't have `shadowAccount` object class, it's added automatically
2. `shadowExpire` attribute is set to: `floor(timestamp / 86400)` (days since epoch)
3. Audit log entry: `account_expiry_set`

**Example calculation:**
```
Expiration date: 2026-01-01 00:00:00 UTC
Unix timestamp:  1735689600
Days since epoch: 1735689600 / 86400 = 20082
shadowExpire:    20082
```

#### 2. Login-Time Enforcement

**On every login:**
1. Luminary checks if user has `shadowExpire` attribute
2. Calculates: `days_remaining = shadowExpire - current_days`
3. If `days_remaining < 0`: **Account is expired**
   - Login blocked
   - User redirected to account_expired.php
   - Shows expiration date and "contact admin" message
4. If `0 <= days_remaining <= ACCOUNT_EXPIRY_WARNING_DAYS`: **Warning shown**
   - Login allowed
   - Warning banner displayed: "Your account expires in X days"
5. If `days_remaining > ACCOUNT_EXPIRY_WARNING_DAYS`: **No action**
   - Login proceeds normally

#### 3. User Self-Service View

Users can see their own expiration date:
- Home → My Profile → Account Information card
- Shows: "Account expires: January 1, 2026 (in 45 days)"
- Warning badge if within warning period

### Account Locking (ppolicy)

**Requires:** `PPOLICY_ENABLED=true` and ppolicy overlay

#### Automatic Locking

ppolicy can automatically lock accounts after repeated failed login attempts:

**OpenLDAP ppolicy configuration:**
```ldif
dn: cn=default,ou=policies,dc=example,dc=com
objectClass: pwdPolicy
pwdMaxFailure: 5           # Lock after 5 failed attempts
pwdLockout: TRUE           # Enable account lockout
pwdLockoutDuration: 1800   # Lock for 30 minutes (0 = forever)
```

**What happens:**
1. User fails login 5 times
2. OpenLDAP sets `pwdAccountLockedTime` attribute
3. All subsequent logins fail until:
   - Lockout duration expires (automatic unlock), OR
   - Admin manually unlocks account

#### Manual Unlocking

**Via Web UI:**
- Admin → Account Manager → Click user → Edit Lifecycle tab
- If account is locked, "Unlock Account" button appears
- Click to unlock

**What happens:**
1. `pwdAccountLockedTime` attribute is deleted
2. Account can log in immediately
3. Audit log entry: `account_unlocked`

### Account Removal vs. Expiration

**Expiration** (reversible):
- Sets `shadowExpire` attribute
- Account still exists in directory
- Admin can extend expiration date
- User data preserved

**Deletion** (permanent):
- Removes user entry from LDAP
- All user data lost
- Cannot be undone without backup

**Best practice:** Use expiration for temporary situations, deletion only when data must be removed.

## Use Cases

### Temporary Contractors

**Scenario:** Hire contractor for 6-month project

**Setup:**
1. Create user account
2. Set expiration date: 6 months from today
3. Set warning period: 14 days

**Result:**
- Contractor works normally for ~5.5 months
- At 14 days remaining, warning appears: "Your account expires in 14 days"
- On expiration date, contractor cannot log in
- Admin can extend expiration if contract extended

### Student Accounts

**Scenario:** University provides accounts for semester (4 months)

**Setup:**
1. Bulk create student accounts (via LDIF import)
2. Set `shadowExpire` to end of semester
3. Set `ACCOUNT_EXPIRY_WARNING_DAYS=30`

**Result:**
- Students notified 30 days before account expires
- Graduated students automatically locked out
- Continuing students' expiration extended by admin

### Trial Access

**Scenario:** Provide 30-day trial access to new service

**Setup:**
1. Create trial user account
2. Set expiration: 30 days from today
3. Set warning: 7 days

**Result:**
- Trial user works normally for 23 days
- Warning appears at day 23: "Your account expires in 7 days"
- At day 30, access revoked automatically
- If user subscribes, admin removes expiration

### Security: Dormant Account Cleanup

**Scenario:** Disable accounts inactive for 90 days

**Manual process:**
1. Check `modifyTimestamp` operational attribute
2. If account not modified in 90 days, set expiration to today
3. Account locked immediately on next login attempt

**Future enhancement:** Automated dormant account detection and expiration (not yet implemented).

## Admin Interface

### View Account Expiration

**Location:** Admin → Account Manager → Click user

**Account Information card shows:**
- "Account expires: January 1, 2026 (in 45 days)" - if set
- "Account does not expire" - if not set
- "Account expired X days ago" - if already expired

### Set Account Expiration

**Location:** Admin → Account Manager → Click user → Edit Lifecycle tab

**Steps:**
1. Select expiration date from calendar picker
2. Click "Set expiration"
3. Confirmation message: "Account expiration set to January 1, 2026"

**Validation:**
- Cannot set expiration date in the past (must be future date)
- Date must be valid calendar date

### Remove Account Expiration

**Location:** Admin → Account Manager → Click user → Edit Lifecycle tab

**Steps:**
1. Click "Remove expiration" button
2. Confirmation: "Are you sure you want to remove the account expiration?"
3. Click "Confirm"

**What happens:**
- `shadowExpire` attribute deleted
- Account never expires (until expiration set again)

### Unlock Locked Account

**Location:** Admin → Account Manager → Click user → Edit Lifecycle tab

**Visible only if:** `PPOLICY_ENABLED=true` and account is locked

**Steps:**
1. Click "Unlock account" button
2. Account unlocked immediately
3. User can log in on next attempt

## Security Considerations

### Expiration Date Visibility

**Users can see:**
- Their own expiration date (via My Profile page)
- Their own warning messages

**Users cannot:**
- Modify their own expiration date
- See other users' expiration dates

**Admins can:**
- View all users' expiration dates
- Modify any user's expiration date
- Remove expiration dates

### Access Control Lists (ACLs)

Ensure LDAP ACLs protect `shadowExpire` attribute:

```ldif
# Recommended ACL
olcAccess: to attrs=shadowExpire
  by self read
  by dn="cn=admin,dc=example,dc=com" write
  by * none
```

This prevents users from extending their own expiration.

### Audit Trail

All lifecycle actions are logged:
- `account_expiry_set` - When admin sets expiration
- `account_expiry_removed` - When admin removes expiration
- `account_unlocked` - When admin unlocks account
- `login_failure` - When expired user attempts login

See [Audit Logging Documentation](audit_logging.md) for details.

### Time Synchronization

**Critical:** Account expiration relies on accurate system time.

**Best practice:**
- Use NTP (Network Time Protocol) to sync time
- Monitor time drift
- Set timezone consistently across all servers

**Check time sync:**
```bash
# Docker container
docker exec luminary date

# Compare to host
date

# Check NTP status (host)
timedatectl status
```

### Grace Period vs. Warning Period

**Warning period** (`ACCOUNT_EXPIRY_WARNING_DAYS`):
- Shows warning but allows login
- User can still work normally
- Gives time to request extension

**No grace period for expired accounts:**
- Login immediately blocked on expiration date
- No "grace logins" after expiration
- Admin must extend expiration to restore access

## Troubleshooting

### Account expiration not working

**Cause 1:** Lifecycle feature not enabled

**Solution:**
```bash
docker exec luminary printenv | grep LIFECYCLE
# Should show: LIFECYCLE_ENABLED=TRUE
# Should show: ACCOUNT_EXPIRY_ENABLED=TRUE
```

---

**Cause 2:** User missing shadowAccount object class

**Check:**
```bash
ldapsearch -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=people,dc=example,dc=com" \
  "(uid=jsmith)" objectClass
```

**Solution:**
Luminary automatically adds `shadowAccount` when setting expiration. If manual LDIF modification:

```ldif
dn: uid=jsmith,ou=people,dc=example,dc=com
changetype: modify
add: objectClass
objectClass: shadowAccount
```

---

**Cause 3:** shadowExpire value incorrect

**Check:**
```bash
ldapsearch -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=people,dc=example,dc=com" \
  "(uid=jsmith)" shadowExpire

# Calculate expected value:
date -d "2026-01-01" +%s  # Unix timestamp
# Divide by 86400 to get days since epoch
```

**Solution:**
Use Luminary web UI to set expiration (handles calculation automatically).

### Warning not showing

**Cause:** Days remaining exceeds `ACCOUNT_EXPIRY_WARNING_DAYS`

**Example:**
- Expiration date: 30 days from now
- `ACCOUNT_EXPIRY_WARNING_DAYS=14`
- Warning appears only when ≤14 days remaining

**Solution:**
Increase `ACCOUNT_EXPIRY_WARNING_DAYS` if you want earlier warnings.

### Unlock button not visible

**Cause 1:** PPOLICY_ENABLED not set

**Solution:**
```bash
docker run -e PPOLICY_ENABLED=true luminary
```

---

**Cause 2:** ppolicy overlay not configured on LDAP server

**Check:**
```bash
ldapsearch -x -H ldap://localhost -b "" -s base \
  "(objectClass=*)" supportedControl | grep 1.3.6.1.4.1.42.2.27.8.5.1
```

If not found, ppolicy overlay not loaded. See [Password Policy Documentation](password_policy.md).

---

**Cause 3:** Account not actually locked

**Check:**
```bash
ldapsearch -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=people,dc=example,dc=com" \
  "(uid=jsmith)" pwdAccountLockedTime
```

If `pwdAccountLockedTime` not present, account is not locked.

### Expired user can still log in

**Cause 1:** Time zone mismatch

**Check:**
```bash
# Container time
docker exec luminary date

# Expected expiration in UTC
date -u -d "2026-01-01" +%s
```

**Solution:** Ensure consistent timezone configuration.

---

**Cause 2:** shadowExpire value in the future

**Check:**
```bash
# Get current days since epoch
echo $(( $(date +%s) / 86400 ))

# Compare to shadowExpire value
ldapsearch ... "(uid=jsmith)" shadowExpire
```

**Solution:** Verify expiration date calculation is correct.

## Migration and Bulk Operations

### Adding shadowAccount to Existing Users

**LDIF modification:**

```ldif
# Add shadowAccount object class to all users
dn: uid=jsmith,ou=people,dc=example,dc=com
changetype: modify
add: objectClass
objectClass: shadowAccount

dn: uid=jdoe,ou=people,dc=example,dc=com
changetype: modify
add: objectClass
objectClass: shadowAccount
```

**Apply:**
```bash
ldapmodify -x -D "cn=admin,dc=example,dc=com" -w password -f add-shadow.ldif
```

### Bulk Setting Expiration Dates

**Example:** Set all student accounts to expire at end of semester

**LDIF:**
```ldif
# Set expiration for all students (assuming in ou=students)
dn: uid=student1,ou=students,dc=example,dc=com
changetype: modify
replace: shadowExpire
shadowExpire: 20454

dn: uid=student2,ou=students,dc=example,dc=com
changetype: modify
replace: shadowExpire
shadowExpire: 20454
```

**Generate LDIF programmatically:**

```bash
#!/bin/bash
EXPIRY_DATE="2026-05-31"
EXPIRY_DAYS=$(( $(date -d "$EXPIRY_DATE" +%s) / 86400 ))

ldapsearch -LLL -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=students,dc=example,dc=com" "(objectClass=inetOrgPerson)" dn | \
  grep "^dn:" | while read dn_line; do
    echo "$dn_line"
    echo "changetype: modify"
    echo "replace: shadowExpire"
    echo "shadowExpire: $EXPIRY_DAYS"
    echo ""
  done > bulk-expiry.ldif

ldapmodify -x -D "cn=admin,dc=example,dc=com" -w password -f bulk-expiry.ldif
```

### Removing Expiration from All Users

**LDIF:**
```ldif
dn: uid=jsmith,ou=people,dc=example,dc=com
changetype: modify
delete: shadowExpire

dn: uid=jdoe,ou=people,dc=example,dc=com
changetype: modify
delete: shadowExpire
```

## Best Practices

### 1. Set Expiration on Creation

For known-temporary accounts (contractors, trials), set expiration at creation time:

1. Create account
2. Immediately set expiration date
3. Document reason in user notes/description field

### 2. Use Appropriate Warning Period

- **Short-term accounts** (< 30 days): 3-7 day warning
- **Medium-term accounts** (3-12 months): 14 day warning
- **Long-term accounts** (> 1 year): 30 day warning

### 3. Monitor Upcoming Expirations

Regularly review accounts expiring soon:

**Manual check:**
- Admin → Account Manager → Sort by expiration date (future feature)

**LDAP query:**
```bash
# Find accounts expiring in next 30 days
CURRENT_DAYS=$(( $(date +%s) / 86400 ))
FUTURE_DAYS=$(( CURRENT_DAYS + 30 ))

ldapsearch -x -D "cn=admin,dc=example,dc=com" -w password \
  -b "ou=people,dc=example,dc=com" \
  "(&(shadowExpire>=*)(shadowExpire<=$FUTURE_DAYS))" uid shadowExpire
```

### 4. Document Expiration Reason

Use LDAP `description` field to note why account expires:

```ldif
dn: uid=contractor1,ou=people,dc=example,dc=com
description: Contract ends May 31, 2026 - Project Phoenix
shadowExpire: 20454
```

### 5. Extend Rather Than Recreate

If account needs extension, modify expiration date rather than deleting and recreating:

**Preserves:**
- User's group memberships
- MFA enrolment
- Historical audit logs
- Email and other attributes

### 6. Regular Audit

Monthly review:
- Accounts that expired but not deleted
- Accounts expiring in next 60 days
- Accounts without expiration that should have one (contractors in permanent groups)

## Future Enhancements

Planned features (not yet implemented):

- **Automated dormant account detection** - Auto-expire accounts inactive >90 days
- **Email notifications** - Send expiration warnings to users via email
- **Bulk expiration UI** - Set expiration for multiple users at once
- **Account lifecycle dashboard** - Visual overview of expiring accounts
- **Custom expiration rules** - Different expiration periods per group/OU

## References

- [RFC 2307 - LDAP as a Network Information Service](https://www.rfc-editor.org/rfc/rfc2307)
- [shadowAccount schema](https://ldapwiki.com/wiki/ShadowAccount)
- [OpenLDAP ppolicy overlay](https://www.openldap.org/doc/admin24/overlays.html#Password%20Policies)
- [Account lifecycle best practices (NIST)](https://csrc.nist.gov/publications/detail/sp/800-53/rev-5/final)

## See Also

- [Password Policy Documentation](password_policy.md) - ppolicy overlay setup
- [Audit Logging Documentation](audit_logging.md) - Track lifecycle events
- [MFA Documentation](mfa.md) - Multi-factor authentication setup
- [Configuration Reference](configuration.md) - All available settings
