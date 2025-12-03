# Configuration Guide

This guide provides detailed instructions and examples for configuring Luminary. For a complete reference of all configuration options, see [configuration.md](configuration.md).

## Table of Contents

- [Quick Start](#quick-start)
- [Using Docker Secrets](#using-docker-secrets)
- [Web Server Configuration](#web-server-configuration)
- [Advanced LDAP Configuration](#advanced-ldap-configuration)
- [User Profile Customization](#user-profile-customization)
- [Username and Display Name Handling](#username-and-display-name-handling)
- [Email Configuration](#email-configuration)
- [Common Configuration Scenarios](#common-configuration-scenarios)
- [Security Best Practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)

---

## Quick Start

The minimum required configuration for Luminary:

```yaml
version: '3'
services:
  luminary:
    image: wheelybird/luminary:latest
    ports:
      - "443:443"
      - "80:80"
    environment:
      # Mandatory settings
      LDAP_URI: ldap://ldap.example.com
      LDAP_BASE_DN: dc=example,dc=com
      LDAP_ADMIN_BIND_DN: cn=admin,dc=example,dc=com
      LDAP_ADMIN_BIND_PWD: YourSecurePassword

      # Recommended settings
      ORGANISATION_NAME: Example Ltd
      LDAP_REQUIRE_STARTTLS: "TRUE"
```

---

## Using Docker Secrets

For sensitive configuration values like passwords, use Docker secrets or file-based configuration instead of environment variables:

### Docker Swarm with Secrets

```bash
# Create secrets
echo "my-ldap-password" | docker secret create ldap_admin_pwd -
echo "my-smtp-password" | docker secret create smtp_pwd -

# Deploy service
docker service create \
  --name luminary \
  --secret ldap_admin_pwd \
  --secret smtp_pwd \
  -e LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_pwd \
  -e SMTP_PASSWORD_FILE=/run/secrets/smtp_pwd \
  -e LDAP_URI=ldap://ldap.example.com \
  -e LDAP_BASE_DN=dc=example,dc=com \
  -e LDAP_ADMIN_BIND_DN=cn=admin,dc=example,dc=com \
  wheelybird/luminary
```

### Docker Compose with Secret Files

```yaml
version: '3'
services:
  luminary:
    image: wheelybird/luminary:latest
    volumes:
      - /secure/path/ldap-password.txt:/run/secrets/ldap_admin_pwd:ro
      - /secure/path/smtp-password.txt:/run/secrets/smtp_pwd:ro
    environment:
      LDAP_ADMIN_BIND_PWD_FILE: /run/secrets/ldap_admin_pwd
      SMTP_PASSWORD_FILE: /run/secrets/smtp_pwd
      # ... other settings
```

### File-Based Configuration Pattern

**Any environment variable can use the `_FILE` suffix** to read from a file instead of being set directly:

- `LDAP_ADMIN_BIND_PWD_FILE` instead of `LDAP_ADMIN_BIND_PWD`
- `SMTP_PASSWORD_FILE` instead of `SMTP_PASSWORD`
- `LDAP_TLS_CACERT_FILE` instead of `LDAP_TLS_CACERT`

This is the recommended approach for production deployments.

---

## Web Server Configuration

### Custom Ports

By default, Luminary listens on ports 80 and 443, with HTTP redirecting to HTTPS.

#### `SERVER_PORT`

Set a custom port for the webserver inside the container. When set, HTTP redirection is disabled.

**Use case:** Running with Docker's `--network=host` mode or when your reverse proxy handles SSL.

```bash
# Listen on port 8443 for HTTPS
docker run -e SERVER_PORT=8443 -e NO_HTTPS=FALSE wheelybird/luminary

# Listen on port 8080 for HTTP (development only)
docker run -e SERVER_PORT=8080 -e NO_HTTPS=TRUE wheelybird/luminary
```

### Disabling HTTPS

#### `NO_HTTPS`

**Default:** `FALSE`

Set to `TRUE` to disable HTTPS and serve over unencrypted HTTP.

```bash
docker run -e NO_HTTPS=TRUE wheelybird/luminary
```

**⚠️ Security Warning:** Only use this for development/testing. Always use HTTPS in production.

### Custom SSL Certificates

#### `SERVER_CERT_FILENAME` / `SERVER_KEY_FILENAME`

**Defaults:** `server.crt` / `server.key`

Provide your own SSL certificates by mounting them and specifying the filenames:

```bash
docker run \
  -v /path/to/your/certs:/opt/ssl \
  -e SERVER_CERT_FILENAME=my-cert.crt \
  -e SERVER_KEY_FILENAME=my-key.key \
  wheelybird/luminary
```

#### `CA_CERT_FILENAME`

If your certificate was signed by an intermediate CA, provide the CA certificate:

```bash
docker run \
  -v /path/to/certs:/opt/ssl \
  -e SERVER_CERT_FILENAME=my-cert.crt \
  -e SERVER_KEY_FILENAME=my-key.key \
  -e CA_CERT_FILENAME=ca-bundle.crt \
  wheelybird/luminary
```

### Reverse Proxy Configuration

#### `SERVER_PATH`

**Default:** `/`

The path where Luminary is served, useful when running behind a reverse proxy at a subpath.

**Examples:**
- `SERVER_PATH=/ldap/` - Served at https://example.com/ldap/
- `SERVER_PATH=/users/` - Served at https://example.com/users/

**Nginx reverse proxy example:**

```nginx
location /ldap/ {
    proxy_pass https://luminary:443/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

```bash
docker run -e SERVER_PATH=/ldap/ wheelybird/luminary
```

---

## Advanced LDAP Configuration

### TLS/SSL Certificate Validation

#### `LDAP_TLS_CACERT` / `LDAP_TLS_CACERT_FILE`

Provide a CA certificate to validate the LDAP server's certificate when using StartTLS or LDAPS.

**Method 1 - Environment variable:**

```bash
docker run -e LDAP_TLS_CACERT="$(cat /path/to/ca-cert.pem)" wheelybird/luminary
```

**Method 2 - File (recommended):**

```bash
docker run \
  -v /path/to/ca-cert.pem:/run/secrets/ldap_ca \
  -e LDAP_TLS_CACERT_FILE=/run/secrets/ldap_ca \
  wheelybird/luminary
```

### Group Membership Attributes

These are auto-detected, but can be overridden if needed:

#### `LDAP_GROUP_MEMBERSHIP_ATTRIBUTE`

**Auto-detected based on RFC2307BIS**

The attribute used to define group membership.

**Common values:**
- `memberUid` - Standard RFC2307 (stores username)
- `member` - RFC2307BIS (stores full DN)

```bash
docker run -e LDAP_GROUP_MEMBERSHIP_ATTRIBUTE=member wheelybird/luminary
```

#### `LDAP_GROUP_MEMBERSHIP_USES_UID`

**Auto-detected**

Set to `TRUE` if group membership uses UIDs instead of full DNs.

**Values:**
- `TRUE` - memberUid stores usernames
- `FALSE` - member stores full DNs

```bash
docker run -e LDAP_GROUP_MEMBERSHIP_USES_UID=TRUE wheelybird/luminary
```

### Custom ObjectClasses and Attributes

Add extra objectClasses and attributes when creating users or groups.

#### `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES`

Comma-separated list of objectClasses to add to user accounts:

```bash
docker run \
  -e LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=mailAccount,customUser \
  wheelybird/luminary
```

#### `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES`

JSON format for additional user attributes. See [Advanced Topics](advanced.md#custom-attributes) for details:

```bash
docker run \
  -e 'LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES={"loginShell":"shell","homeDirectory":"text"}' \
  wheelybird/luminary
```

#### `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES`

Comma-separated list of objectClasses to add to groups:

```bash
docker run \
  -e LDAP_GROUP_ADDITIONAL_OBJECTCLASSES=customGroup \
  wheelybird/luminary
```

#### `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`

JSON format for additional group attributes:

```bash
docker run \
  -e 'LDAP_GROUP_ADDITIONAL_ATTRIBUTES={"description":"text"}' \
  wheelybird/luminary
```

---

## User Profile Customization

### Understanding USER_EDITABLE_ATTRIBUTES

The `USER_EDITABLE_ATTRIBUTES` environment variable controls which LDAP attributes users can edit in their self-service profile.

**Built-in safe defaults** (always editable):
- `telephonenumber` - Telephone Number
- `mobile` - Mobile Number
- `displayname` - Display Name
- `description` - About Me
- `title` - Job Title
- `jpegphoto` - Profile Photo (JPEG only, max 500KB)
- `sshpublickey` - SSH Public Keys

### Format

```
attribute:Label::InputType
```

**Components:**
- `attribute` - LDAP attribute name (required)
- `:Label` - User-friendly label (optional, defaults to attribute name)
- `::InputType` - HTML input type (optional, defaults to text)

### Input Types

- `text` - Single-line text input (default)
- `textarea` - Multi-line text area
- `tel` - Telephone number (mobile keyboard support)
- `email` - Email address with validation
- `url` - URL with validation
- `checkbox` - Boolean checkbox (TRUE/FALSE)
- `multipleinput` - Multiple values with + button
- `binary` - File upload (for images, certificates)

### Suffix Shortcuts

- `attribute+` - Multi-valued attribute (same as `::multipleinput`)
- `attribute^` - Binary/file upload (same as `::binary`)

### Examples

**Simple attribute with default label:**

```bash
USER_EDITABLE_ATTRIBUTES="personalTitle"
```

**Attribute with custom label:**

```bash
USER_EDITABLE_ATTRIBUTES="personalTitle:Job Title"
```

**Multiple attributes:**

```bash
USER_EDITABLE_ATTRIBUTES="personalTitle:Job Title,office:Office Location,bio:Biography::textarea"
```

**Telephone with specific input type:**

```bash
USER_EDITABLE_ATTRIBUTES="workPhone:Work Phone::tel"
```

**Multi-valued attribute using suffix:**

```bash
USER_EDITABLE_ATTRIBUTES="sshPublicKey+"
```

**File upload using suffix:**

```bash
USER_EDITABLE_ATTRIBUTES="avatar:Profile Picture^"
```

**Complex example:**

```bash
USER_EDITABLE_ATTRIBUTES="personalTitle:Job Title,bio:Biography::textarea,sshPublicKey+,avatar^,office:Office,website:Website::url,availableForChat:Available::checkbox"
```

### Security Blacklist

A **security blacklist** prevents users from editing critical system attributes, regardless of configuration:

**Blacklisted attributes** (cannot be user-edited):
- **System identifiers:** `dn`, `uid`, `cn`, `objectClass`
- **POSIX attributes:** `uidNumber`, `gidNumber`, `homeDirectory`, `loginShell`
- **Security:** `userPassword`, `sambaNTPassword`, `sambaPassword`
- **Group membership:** `memberOf`, `member`, `memberUid`, `uniqueMember`
- **MFA/TOTP:** `totpSecret`, `totpStatus`, `totpEnrolledDate`, `totpScratchCode`
- **Structural:** `creatorsName`, `createTimestamp`, `modifiersName`, `modifyTimestamp`, `entryDN`, `entryUUID`, `structuralObjectClass`, `hasSubordinates`, `subschemaSubentry`

Attempts to edit blacklisted attributes will be logged and rejected.

### Photo Upload Validation

The `jpegPhoto` attribute has special validation:
- **File type:** Must be a valid JPEG image (verified by MIME type and image content)
- **File size:** Maximum 500KB (to ensure LDAP performance)
- **Format:** Binary data stored directly in LDAP

Users attempting to upload non-JPEG files or files larger than 500KB will see an error message.

### Real-World Configurations

**Extended employee profile:**

```bash
USER_EDITABLE_ATTRIBUTES="personalTitle:Job Title,office:Office Location,bio:About Me::textarea,workPhone:Work Phone::tel,website:Personal Website::url"
```

**IT department with SSH keys:**

```bash
USER_EDITABLE_ATTRIBUTES="sshPublicKey:SSH Keys+,personalTitle:Role"
```

**Custom organizational schema:**

```bash
USER_EDITABLE_ATTRIBUTES="employeeID:Employee ID,costCenter:Cost Center,projectCode:Current Project,supervisor:Manager"
```

---

## Username and Display Name Handling

### Understanding Unicode and ASCII Conversion

Luminary supports international names, but usernames must be ASCII due to LDAP and POSIX requirements.

#### Why Usernames Are ASCII-Only

**LDAP Schema Constraints:**

The following standard LDAP attributes use **IA5String syntax** (ASCII-only, codes 0-127):
- `mail` (email address) - RFC 4524
- `homeDirectory` (home path) - RFC 2307/2307bis
- `uid` (username) - RFC 2307

While modern standards exist (EAI for email/RFC 6531, UTF-8 filesystems), **LDAP schema definitions remain ASCII-only** for these attributes. This is a schema constraint, not an OS limitation:

- **Linux/Unix:** Modern filesystems (ext4, XFS, Btrfs) fully support UTF-8 paths like `/home/hæppy`
- **Email Systems:** Modern MTAs support Unicode addresses via EAI (RFC 6531)
- **LDAP Schema:** Still uses 1990s-era IA5String for compatibility

**Display name attributes** (`cn`, `givenName`, `sn`) DO support Unicode via DirectoryString syntax - only technical identifiers (username, email, paths) are ASCII-restricted.

### ENFORCE_USERNAME_VALIDATION

**Default:** `TRUE`

Controls username validation and Common Name (CN) formatting.

#### When TRUE (default):
- Usernames must match the `USERNAME_REGEX` pattern
- CN is formatted without spaces and with accents removed
  - Example: "Hæppy Testør" → CN: `haeppytestor`
- Validation errors shown if invalid characters used

#### When FALSE:
- Username validation is skipped (more permissive)
- CN preserves spaces and Unicode
  - Example: "Hæppy Testør" → CN: `Hæppy Testør`

**Important:** Usernames are ALWAYS converted to ASCII (regardless of this setting) for compatibility:
- User "Hæppy Testør" → username: `haeppy-testor` (always ASCII-safe)

#### Examples:

**Strict mode (default):**
```bash
ENFORCE_USERNAME_VALIDATION=TRUE
# User: José García
# Username: jose-garcia (ASCII)
# CN: josegarcia (no spaces, no accents)
```

**Relaxed mode:**
```bash
ENFORCE_USERNAME_VALIDATION=FALSE
# User: José García
# Username: jose-garcia (ASCII)
# CN: José García (preserves Unicode and spaces)
```

### USERNAME_FORMAT

**Default:** `{first_name}-{last_name}`

Template for auto-generating usernames. Spaces and hyphens in names are automatically removed.

**Available placeholders:**
- `{first_name}` - User's full first name
- `{first_name_initial}` - First letter of first name
- `{last_name}` - User's full last name
- `{last_name_initial}` - First letter of last name

**Examples:**

| Format | Input: John Smith | Input: Jean-Paul Dubois |
|--------|-------------------|-------------------------|
| `{first_name}-{last_name}` | john-smith | jeanpaul-dubois |
| `{first_name}.{last_name}` | john.smith | jeanpaul.dubois |
| `{first_name_initial}{last_name}` | jsmith | jdubois |
| `{last_name}{first_name_initial}` | smithj | duboisj |
| `{first_name_initial}{last_name_initial}` | js | jd |
| `{first_name_initial}.{last_name}` | j.smith | j.dubois |
| `{last_name}-{first_name}` | smith-john | dubois-jeanpaul |

**Note:** All generated usernames are automatically converted to lowercase and ASCII.

### USERNAME_REGEX

**Default:** `^[\p{L}\p{N}_.-]{2,64}$`

Regular expression for validating usernames and group names. Supports Unicode for international names.

**Pattern explanation:**
- `\p{L}` - Any Unicode letter (supports international characters)
- `\p{N}` - Any Unicode number
- `_.-` - Underscore, period, and hyphen are allowed
- `{2,64}` - Length between 2 and 64 characters

**Note:** This regex validates the format/length, but usernames are always converted to ASCII. For example, "José" passes this regex but becomes username "jose".

---

## Email Configuration

### SMTP Setup

Email features require SMTP configuration. All settings are optional except `SMTP_HOSTNAME`.

**Basic configuration:**

```yaml
environment:
  SMTP_HOSTNAME: smtp.gmail.com
  SMTP_HOST_PORT: 587
  SMTP_USERNAME: notifications@example.com
  SMTP_PASSWORD_FILE: /run/secrets/smtp_pwd
  SMTP_USE_TLS: "TRUE"
  EMAIL_FROM_ADDRESS: noreply@example.com
  EMAIL_FROM_NAME: Example Ltd User Management
```

### Common SMTP Ports

- `25` - Standard SMTP (usually blocked by ISPs)
- `587` - SMTP with StartTLS (recommended, use `SMTP_USE_TLS=TRUE`)
- `465` - SMTPS (SMTP over SSL, use `SMTP_USE_SSL=TRUE`)

**Note:** Don't set both `SMTP_USE_TLS` and `SMTP_USE_SSL` to TRUE.

### Email Domain for Auto-Generation

#### `EMAIL_DOMAIN`

When set and a user's email is blank, generates as `username@example.com`:

```bash
EMAIL_DOMAIN=example.com
# User "john-smith" gets email: john-smith@example.com
```

**Note:** Email addresses are always ASCII-only due to LDAP schema constraints. Usernames are transliterated to ASCII before generating email addresses.

### Gmail Example

```yaml
environment:
  SMTP_HOSTNAME: smtp.gmail.com
  SMTP_HOST_PORT: 587
  SMTP_USERNAME: your-email@gmail.com
  SMTP_PASSWORD_FILE: /run/secrets/gmail_app_password
  SMTP_USE_TLS: "TRUE"
  EMAIL_FROM_ADDRESS: your-email@gmail.com
  EMAIL_FROM_NAME: My Organization
```

**Note:** Gmail requires an [App Password](https://support.google.com/accounts/answer/185833) when 2FA is enabled.

### Testing Email Configuration

Enable SMTP debugging to troubleshoot email issues:

```bash
SMTP_LOG_LEVEL=3  # 0=off, 1=client, 2=client+server, 3=verbose, 4=very verbose
```

---

## Common Configuration Scenarios

### Scenario 1: Development/Testing

```yaml
environment:
  LDAP_URI: ldap://ldap:389
  LDAP_BASE_DN: dc=test,dc=local
  LDAP_ADMIN_BIND_DN: cn=admin,dc=test,dc=local
  LDAP_ADMIN_BIND_PWD: testpassword
  NO_HTTPS: "TRUE"
  LDAP_IGNORE_CERT_ERRORS: "TRUE"
  ACCEPT_WEAK_PASSWORDS: "TRUE"
  LDAP_DEBUG: "TRUE"
```

### Scenario 2: Production with MFA

```yaml
environment:
  # LDAP
  LDAP_URI: ldaps://ldap.example.com:636
  LDAP_BASE_DN: dc=example,dc=com
  LDAP_ADMIN_BIND_DN: cn=luminary,ou=services,dc=example,dc=com
  LDAP_ADMIN_BIND_PWD_FILE: /run/secrets/ldap_admin_pwd
  LDAP_REQUIRE_STARTTLS: "TRUE"
  LDAP_TLS_CACERT_FILE: /run/secrets/ldap_ca_cert

  # MFA
  MFA_ENABLED: "TRUE"
  MFA_REQUIRED_GROUPS: admins,developers
  MFA_GRACE_PERIOD_DAYS: 7
  MFA_TOTP_ISSUER: Example Ltd

  # Branding
  ORGANISATION_NAME: Example Ltd
  SITE_NAME: Example Account Management

  # Security
  SESSION_TIMEOUT: 15
  PASSWORD_HASH: SSHA256
```

### Scenario 3: Behind Reverse Proxy

```yaml
environment:
  SERVER_PORT: 8080
  SERVER_PATH: /accounts/
  NO_HTTPS: "TRUE"  # Proxy handles SSL
  REMOTE_HTTP_HEADERS_LOGIN: "TRUE"

  LDAP_URI: ldap://ldap.internal:389
  LDAP_BASE_DN: dc=example,dc=com
  # ... other LDAP settings
```

### Scenario 4: Extended User Profiles

```yaml
environment:
  # ... LDAP settings ...

  USER_EDITABLE_ATTRIBUTES: >
    personalTitle:Job Title,
    office:Office Location,
    bio:Biography::textarea,
    workPhone:Work Phone::tel,
    mobile:Mobile::tel,
    website:Website::url,
    sshPublicKey:SSH Keys+,
    availableForChat:Available for Chat::checkbox

  SHOW_POSIX_ATTRIBUTES: "TRUE"
```

---

## Security Best Practices

### 1. Always Use HTTPS in Production

```yaml
NO_HTTPS: "FALSE"  # Default - do not change for production
```

Provide valid SSL certificates or use a reverse proxy with SSL termination.

### 2. Use Docker Secrets for Passwords

Never put passwords directly in environment variables:

```yaml
# ❌ Bad
LDAP_ADMIN_BIND_PWD: mypassword

# ✅ Good
LDAP_ADMIN_BIND_PWD_FILE: /run/secrets/ldap_admin_pwd
```

### 3. Enable LDAP Encryption

```yaml
# Option 1: Use LDAPS
LDAP_URI: ldaps://ldap.example.com:636

# Option 2: Use StartTLS
LDAP_URI: ldap://ldap.example.com:389
LDAP_REQUIRE_STARTTLS: "TRUE"
```

### 4. Validate Certificates

```yaml
# Do not ignore certificate errors in production
LDAP_IGNORE_CERT_ERRORS: "FALSE"

# Provide CA certificate for validation
LDAP_TLS_CACERT_FILE: /run/secrets/ldap_ca_cert
```

### 5. Enforce Strong Passwords

```yaml
ACCEPT_WEAK_PASSWORDS: "FALSE"  # Default
PASSWORD_HASH: SSHA256  # or SSHA512
```

### 6. Secure Session Management

```yaml
SESSION_TIMEOUT: 15  # Minutes of inactivity
NO_HTTPS: "FALSE"
```

### 7. Limit Editable Attributes

Only allow users to edit safe attributes. The security blacklist prevents editing of:
- System identifiers (uid, uidNumber, gidNumber)
- Passwords
- Group memberships
- MFA secrets

### 8. Review Admin Group Membership

```yaml
LDAP_ADMINS_GROUP: admins  # Review members regularly
```

Members of this group have full access to manage all accounts.

### 9. Disable Debug Logging in Production

```yaml
LDAP_DEBUG: "FALSE"
SESSION_DEBUG: "FALSE"
SMTP_LOG_LEVEL: 0
```

Debug logs may expose sensitive information.

---

## Troubleshooting

### LDAP Connection Issues

**Problem:** Can't connect to LDAP server

**Solutions:**

1. **Check LDAP_URI format:**
   ```bash
   LDAP_URI=ldap://ldap.example.com:389
   # or
   LDAP_URI=ldaps://ldap.example.com:636
   ```

2. **Enable LDAP debugging:**
   ```bash
   LDAP_DEBUG=TRUE
   LDAP_VERBOSE_CONNECTION_LOGS=TRUE
   ```

3. **Check network connectivity:**
   ```bash
   docker exec luminary ping ldap.example.com
   docker exec luminary nc -zv ldap.example.com 389
   ```

4. **Verify certificates (if using LDAPS/StartTLS):**
   ```bash
   LDAP_IGNORE_CERT_ERRORS=TRUE  # Temporarily, to test
   ```

### SSL Certificate Errors

**Problem:** "certificate verify failed" or similar

**Solutions:**

1. **Provide CA certificate:**
   ```bash
   LDAP_TLS_CACERT_FILE=/run/secrets/ldap_ca_cert
   ```

2. **Check certificate validity:**
   ```bash
   openssl s_client -connect ldap.example.com:636 -showcerts
   ```

3. **Temporarily ignore (testing only):**
   ```bash
   LDAP_IGNORE_CERT_ERRORS=TRUE
   ```

### Email Not Sending

**Problem:** Account request emails or notifications not being sent

**Solutions:**

1. **Verify SMTP configuration:**
   ```bash
   SMTP_HOSTNAME=smtp.gmail.com
   SMTP_HOST_PORT=587
   SMTP_USE_TLS=TRUE
   ```

2. **Enable SMTP debugging:**
   ```bash
   SMTP_LOG_LEVEL=3
   ```

3. **Check credentials:**
   - Gmail requires App Passwords with 2FA
   - Some providers block port 25

4. **Test SMTP connectivity:**
   ```bash
   docker exec luminary nc -zv smtp.gmail.com 587
   ```

### Users Can't Edit Their Profiles

**Problem:** User profile page shows no editable fields

**Solutions:**

1. **Check default editable attributes:**
   The built-in defaults should work: telephonenumber, mobile, displayname, description, title, jpegphoto, sshpublickey

2. **Verify attribute blacklist:**
   Ensure you're not trying to make blacklisted attributes editable

3. **Check LDAP schema:**
   Verify the LDAP server supports the attributes you want users to edit

### MFA Not Working

**Problem:** MFA enrolment or validation fails

**Solutions:**

1. **Verify MFA is enabled:**
   ```bash
   MFA_ENABLED=TRUE
   ```

2. **Check LDAP schema:**
   Ensure the TOTP schema is installed (see MFA documentation)

3. **Verify time synchronization:**
   TOTP requires accurate system time (NTP)

4. **Check grace period:**
   ```bash
   MFA_GRACE_PERIOD_DAYS=7
   ```

### Performance Issues with Large Directories

**Problem:** Slow loading with many users

**Solutions:**

1. **Disable debug logging:**
   ```bash
   LDAP_DEBUG=FALSE
   LDAP_VERBOSE_CONNECTION_LOGS=FALSE
   ```

2. **Optimize LDAP queries:**
   - Ensure LDAP server has proper indexes
   - Check LDAP server performance

3. **Reduce session timeout:**
   ```bash
   SESSION_TIMEOUT=10
   ```

### Unicode/International Character Issues

**Problem:** International characters not displaying correctly

**Solutions:**

1. **Understand ASCII username requirement:**
   Usernames MUST be ASCII due to LDAP schema constraints

2. **Set ENFORCE_USERNAME_VALIDATION appropriately:**
   ```bash
   # Preserve Unicode in display names
   ENFORCE_USERNAME_VALIDATION=FALSE
   ```

3. **Use appropriate attributes:**
   - Display names (cn, givenName, sn) support Unicode
   - Technical fields (uid, mail, homeDirectory) are ASCII-only

---

## Getting More Help

- **System Config Page:** View current configuration at Main Menu → System Config
- **Configuration Reference:** See [configuration.md](configuration.md) for all options
- **MFA Guide:** See [mfa.md](mfa.md) for multi-factor authentication setup
- **Advanced Topics:** See [advanced.md](advanced.md) for complex scenarios
- **GitHub Issues:** https://github.com/wheelybird/ldap-user-manager/issues

---

*Last updated: 2025-11-10*
