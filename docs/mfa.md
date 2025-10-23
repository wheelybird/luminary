# Multi-Factor Authentication

LDAP User Manager includes comprehensive support for TOTP (Time-based One-Time Password) multi-factor authentication, with TOTP secrets stored directly in your LDAP directory.

## Overview

The MFA system provides:

- **Self-Service Enrolment**: Users scan QR codes with their authenticator apps
- **Group-Based Enforcement**: Require MFA for specific groups (e.g., admins, developers)
- **Grace Periods**: Give users time to set up MFA before enforcement
- **Backup Codes**: Emergency recovery codes stored in LDAP
- **Admin Management**: Admins can view MFA status and regenerate backup codes
- **Integration**: Works seamlessly with [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp)

## Requirements

### 1. TOTP LDAP Schema

The MFA features require a custom LDAP schema to store TOTP configuration. Install the schema from:

**https://github.com/wheelybird/ldap-totp-schema**

This adds the following to your LDAP directory:
- `totpUser` objectClass for users with MFA
- `totpSecret` attribute for the TOTP shared secret
- `totpStatus` attribute for enrolment status
- `totpEnrolledDate` attribute for tracking grace periods
- `totpScratchCode` attribute for backup codes

### 2. Authenticator App

Users need a TOTP authenticator app on their mobile device:

- **Google Authenticator** (iOS/Android)
- **Microsoft Authenticator** (iOS/Android)
- **Authy** (iOS/Android/Desktop)
- **FreeOTP** (iOS/Android)
- **Any RFC 6238 compliant TOTP app**

## Configuration

### Basic Setup

Enable MFA and configure enforcement:

```bash
docker run \
  -e MFA_ENABLED=TRUE \
  -e MFA_REQUIRED_GROUPS=admins,developers \
  -e MFA_GRACE_PERIOD_DAYS=7 \
  -e MFA_TOTP_ISSUER="Example Ltd" \
  wheelybird/ldap-user-manager
```

### Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MFA_ENABLED` | `FALSE` | Enable MFA features |
| `MFA_REQUIRED_GROUPS` | None | Comma-separated list of groups requiring MFA |
| `MFA_GRACE_PERIOD_DAYS` | `7` | Days allowed for MFA setup |
| `MFA_TOTP_ISSUER` | `$ORGANISATION_NAME` | Name shown in authenticator apps |

### Custom Schema Attributes

If you're using a custom TOTP schema with different attribute names:

```bash
-e TOTP_SECRET_ATTRIBUTE=myTotpSecret \
-e TOTP_STATUS_ATTRIBUTE=myTotpStatus \
-e TOTP_ENROLLED_DATE_ATTRIBUTE=myTotpDate \
-e TOTP_SCRATCH_CODES_ATTRIBUTE=myBackupCodes \
-e TOTP_OBJECTCLASS=myTotpUser
```

## User Enrolment Process

### Step 1: User Notification

When a user is added to an MFA-required group:

1. Their account is automatically marked with `totpStatus=pending`
2. The grace period countdown begins
3. They see warnings on the home page prompting them to set up MFA

### Step 2: QR Code Setup

Users navigate to "Manage MFA" and click "Set Up Multi-Factor Authentication":

1. A QR code is displayed with the TOTP secret
2. User scans the QR code with their authenticator app
3. The secret can also be entered manually if QR scanning isn't possible

### Step 3: Verification

To prevent setup errors, users must verify with two consecutive codes:

1. Enter the current 6-digit code from their authenticator
2. Wait 35 seconds for the code to change
3. Enter the new 6-digit code
4. If both codes validate, MFA is activated

### Step 4: Backup Codes

After successful enrolment:

1. 10 backup codes are generated (8 digits each)
2. Codes are displayed once and must be saved
3. Backup codes are stored in LDAP for emergency access
4. Each code can only be used once

## Grace Periods

### How Grace Periods Work

When a user is added to an MFA-required group:

- **Grace Period Active**: User can log in and access systems normally
- **Warning Displays**: Home page shows days remaining to set up MFA
- **Colour Indicators**: Green → Orange → Red as deadline approaches
- **After Expiry**: User cannot access protected systems until MFA is configured

### Grace Period Status

| Days Remaining | Status | User Access |
|----------------|--------|-------------|
| 7-4 days | Green | Full access |
| 3-1 days | Orange | Full access with urgent warnings |
| 0 days | Red | Access denied until MFA configured |
| N/A (MFA active) | Blue | Full access |

### Configuring Grace Periods

```bash
# 14-day grace period
-e MFA_GRACE_PERIOD_DAYS=14

# No grace period (immediate enforcement)
-e MFA_GRACE_PERIOD_DAYS=0
```

## Admin Management

### Viewing MFA Status

Administrators can view each user's MFA status in the user details page:

- **Status**: None, Pending, Active, or Disabled
- **MFA Required**: Whether the user is in an MFA-required group
- **Backup Codes**: Number of remaining backup codes (e.g., "7 remaining")

### Regenerating Backup Codes

If a user has lost their backup codes or used most of them:

1. Admin navigates to the user's details page
2. Clicks "Regenerate Backup Codes"
3. Confirms the action
4. 10 new codes are generated
5. Old unused codes are invalidated
6. User retrieves new codes from their "Manage MFA" page

**Note:** Only administrators can regenerate backup codes. Users cannot regenerate their own codes to prevent circumventing MFA requirements.

### System Status Panel

Administrators see an MFA status panel on the home page showing:

- MFA enabled/disabled status
- TOTP schema installation status
- List of MFA-required groups
- Grace period setting

## Integration with OpenVPN

### Combined Setup

LDAP User Manager works alongside [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) to provide complete MFA for VPN access:

1. **User Management**: Create accounts in LDAP User Manager
2. **MFA Enrolment**: Users enrol via the web interface
3. **LDAP Storage**: TOTP secrets stored in LDAP
4. **VPN Authentication**: OpenVPN server validates password+TOTP
5. **Unified Policy**: Same MFA requirements across web and VPN

### VPN Connection

Once MFA is enabled, users connect to VPN by appending their TOTP code to their password:

```
Username: john.smith
Password: MySecurePassword123456
          ^^^^^^^^^^^^^^^^^^  Your normal password
                        ^^^^^^  Current 6-digit TOTP code
```

The OpenVPN server extracts the code, validates it against the LDAP-stored secret, then verifies the password.

### Backup Code Usage

Users can also use backup codes instead of TOTP:

```
Password: MySecurePassword12345678
          ^^^^^^^^^^^^^^^^^^  Your normal password
                        ^^^^^^^^  8-digit backup code
```

Each backup code can only be used once and is marked as used in LDAP.

## Troubleshooting

### Schema Not Found

**Symptom**: Warning messages about missing TOTP schema

**Solution**:
1. Install the TOTP schema from [ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema)
2. Restart the LDAP User Manager container
3. Check the System Status panel (admin home page) to verify schema detection

### QR Code Won't Scan

**Solutions**:
- Ensure phone camera has permission to access the camera
- Try increasing screen brightness
- Try a different authenticator app
- Use manual entry instead (secret is displayed below QR code)

### Codes Not Working

**Common causes**:
- **Time Sync**: Ensure the device running the authenticator app has correct time
- **Wrong Code**: Codes change every 30 seconds, enter the current code
- **Already Used**: Each code can only be used once
- **Wrong Account**: Ensure you're using the code for the correct account

**Verification**:
```bash
# Check if TOTP secret exists in LDAP
ldapsearch -x -H ldap://localhost -D "cn=admin,dc=example,dc=com" -w password \
  -b "uid=username,ou=people,dc=example,dc=com" totpSecret
```

### User Stuck in Pending Status

**Symptom**: User has enrolled but status shows "Pending"

**Cause**: Grace period tracking may not have been initialized

**Solution**: Admin can regenerate backup codes to reset the status, or user can disable and re-enable MFA.

### Grace Period Not Working

**Symptom**: User blocked immediately after joining MFA-required group

**Checks**:
1. Verify `MFA_GRACE_PERIOD_DAYS` is set to a positive number
2. Check System Status panel shows grace period correctly
3. Verify `totpEnrolledDate` is set in LDAP:
   ```bash
   ldapsearch ... totpEnrolledDate
   ```

## Security Considerations

### TOTP Secret Protection

- Secrets are 160-bit (32 Base32 characters) for strong entropy
- Stored in LDAP with restricted ACLs
- Only readable by self, admins, and authentication services
- Never displayed after initial enrolment

### Backup Code Security

- 8 digits each (100 million combinations)
- Single-use only (marked as used after first use)
- 10 codes provide ample recovery options
- Only admins can regenerate (prevents abuse)

### Time Synchronisation

- TOTP requires accurate system time
- Default tolerance: ±90 seconds (3 time windows)
- Prevents replay attacks
- Monitor with NTP

### Best Practices

1. **Enable MFA for Administrators**: Always require MFA for admin groups
2. **Short Grace Periods**: Use 7 days or less for critical systems
3. **Monitor Enrolment**: Check MFA adoption rates via System Status
4. **Backup Codes**: Remind users to save backup codes securely
5. **Time Sync**: Ensure all servers have accurate time via NTP

## Migration

### Enabling MFA on Existing Systems

When enabling MFA on an established LDAP directory:

1. **Install Schema**: Add the TOTP schema to LDAP
2. **Enable MFA**: Set `MFA_ENABLED=TRUE`
3. **Test First**: Start with a test group, not production admins
4. **Configure Groups**: Set `MFA_REQUIRED_GROUPS` for test group
5. **User Communication**: Notify users before enforcement
6. **Grace Period**: Use a longer grace period initially (e.g., 14 days)
7. **Monitor**: Check System Status panel for enrolment progress
8. **Expand**: Add more groups once process is validated

### Disabling MFA

To temporarily disable MFA:

```bash
-e MFA_ENABLED=FALSE
```

This disables MFA enforcement but preserves existing enrolments. Users who have already enrolled will keep their TOTP configuration in LDAP.

To re-enable, set `MFA_ENABLED=TRUE` and existing enrolments will work immediately.

## Advanced Topics

### Custom TOTP Parameters

The TOTP implementation uses RFC 6238 standards:

- **Algorithm**: SHA-1 (widely compatible)
- **Digits**: 6
- **Time Step**: 30 seconds
- **Window**: ±3 time steps (±90 seconds)

These parameters are not currently configurable but can be modified in the source code if needed for integration with systems requiring different TOTP parameters.

### Multiple Authentication Systems

If using MFA with multiple systems (VPN, SSH, web apps):

1. **Shared LDAP**: All systems read from the same LDAP directory
2. **Same Secret**: One TOTP secret works across all systems
3. **Unified Management**: Users enrol once via LDAP User Manager
4. **Consistent Policy**: MFA requirements apply everywhere

### Audit Logging

MFA events are logged to the PHP error log:

- Enrolment success/failure
- Backup code generation
- Schema validation errors
- Authentication attempts (in auth systems, not user manager)

Enable debug logging to see detailed MFA operations:
```bash
-e LDAP_DEBUG=TRUE
```
