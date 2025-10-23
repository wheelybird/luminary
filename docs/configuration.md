# Configuration Reference

LDAP User Manager is configured entirely through environment variables. This document lists all available configuration options organised by category.

## Table of Contents

- [Mandatory Settings](#mandatory-settings)
- [Web Server Settings](#web-server-settings)
- [LDAP Settings](#ldap-settings)
- [Advanced LDAP Settings](#advanced-ldap-settings)
- [User Account Settings](#user-account-settings)
- [Multi-Factor Authentication](#multi-factor-authentication)
- [Email Settings](#email-settings)
- [Account Request Settings](#account-request-settings)
- [Appearance and Behaviour](#appearance-and-behaviour)
- [Debugging](#debugging)

## Mandatory Settings

These variables must be set for LDAP User Manager to function:

### `LDAP_URI`

The URI of your LDAP server.

**Examples:**
- `ldap://ldap.example.com`
- `ldaps://ldap.example.com:636`
- `ldap://192.168.1.100:389`

### `LDAP_BASE_DN`

The base distinguished name for your LDAP directory. All users and groups will be created under this DN.

**Example:** `dc=example,dc=com`

### `LDAP_ADMIN_BIND_DN`

The DN of an LDAP user with permission to modify all records under `LDAP_BASE_DN`. This is typically the admin user.

**Example:** `cn=admin,dc=example,dc=com`

### `LDAP_ADMIN_BIND_PWD`

The password for the `LDAP_ADMIN_BIND_DN` user.

**Security Note:** Consider using `LDAP_ADMIN_BIND_PWD_FILE` with Docker secrets instead of putting passwords directly in environment variables.

### `LDAP_ADMINS_GROUP`

The name of the LDAP group whose members can access the user management interface.

**Example:** `admins`

**Note:** Members of this group have full access to create, modify, and delete user accounts and groups.

---

## Web Server Settings

Configuration for the web interface and HTTPS.

### `SERVER_HOSTNAME`

**Default:** `ldapusermanager.org`

The hostname that the interface will be served from. This is used for generating URLs and SSL certificate generation.

**Example:** `users.example.com`

### `SERVER_PATH`

**Default:** `/`

The path to the user manager on the webserver. Useful when running behind a reverse proxy at a subpath.

**Examples:**
- `/ldap/` - Served at https://example.com/ldap/
- `/users/` - Served at https://example.com/users/

### `SERVER_PORT`

**Default:** Listens on ports 80 and 443, with HTTP redirecting to HTTPS

The port the webserver inside the container will listen on. When set, HTTP redirection is disabled and the server listens only on this port.

**Usage:** Primarily for when using Docker's `--network=host` mode.

**Examples:**
- `8443` - Listen on port 8443 for HTTPS traffic
- `8080` - Listen on port 8080 for HTTP traffic (requires `NO_HTTPS=TRUE`)

### `NO_HTTPS`

**Default:** `FALSE`

Set to `TRUE` to disable HTTPS and serve the interface over unencrypted HTTP.

**Security Warning:** Only use this for testing. In production, always use HTTPS.

### `SERVER_CERT_FILENAME` / `SERVER_KEY_FILENAME`

**Defaults:** `server.crt` / `server.key`

If you're providing your own SSL certificates (mounted into `/opt/ssl/`), specify the filenames here.

**Example:**
```bash
-v /path/to/certs:/opt/ssl \
-e SERVER_CERT_FILENAME=my-cert.crt \
-e SERVER_KEY_FILENAME=my-key.key
```

### `CA_CERT_FILENAME`

If your certificate was signed by an intermediate CA, provide the CA certificate filename.

**Example:** `ca-bundle.crt`

---

## LDAP Settings

### `LDAP_USER_OU`

**Default:** `people`

The name of the organisational unit where user accounts are stored.

**Full DN will be:** `ou=people,dc=example,dc=com`

### `LDAP_GROUP_OU`

**Default:** `groups`

The name of the organisational unit where groups are stored.

**Full DN will be:** `ou=groups,dc=example,dc=com`

### `LDAP_REQUIRE_STARTTLS`

**Default:** `FALSE`

Set to `TRUE` to require StartTLS for LDAP connections. Recommended for security when using `ldap://` URIs.

**Note:** Not needed when using `ldaps://` URIs which are already encrypted.

### `LDAP_IGNORE_CERT_ERRORS`

**Default:** `FALSE`

Set to `TRUE` to ignore SSL/TLS certificate validation errors.

**Usage:** Useful for testing with self-signed certificates.

**Security Warning:** Do not use in production. Disables certificate verification which protects against man-in-the-middle attacks.

### `LDAP_TLS_CACERT`

Provide a CA certificate to validate the LDAP server's certificate when using StartTLS or LDAPS.

**Usage:** Set this to the contents of your CA certificate file (including BEGIN/END markers).

**Example:**
```bash
-e LDAP_TLS_CACERT="$(cat /path/to/ca-cert.pem)"
```

Or use a file:
```bash
-e LDAP_TLS_CACERT_FILE=/run/secrets/ldap_ca_cert
```

---

## Advanced LDAP Settings

### `LDAP_ACCOUNT_ATTRIBUTE`

**Default:** `uid`

The attribute used to identify user accounts.

**Common values:**
- `uid` - For standard POSIX accounts
- `cn` - For some directory schemas
- `sAMAccountName` - For Active Directory compatibility

### `LDAP_GROUP_ATTRIBUTE`

**Default:** `cn`

The attribute used to identify groups.

### `FORCE_RFC2307BIS`

**Default:** Auto-detected

Set to `TRUE` to force RFC2307BIS schema mode, which allows for `memberOf` searches and enhanced group management.

**Usage:** Only set this if auto-detection fails.

### `LDAP_GROUP_MEMBERSHIP_ATTRIBUTE`

**Default:** Auto-detected based on RFC2307BIS

The attribute used to define group membership.

**Common values:**
- `memberUid` - Standard RFC2307 (stores username)
- `member` - RFC2307BIS (stores full DN)

### `LDAP_GROUP_MEMBERSHIP_USES_UID`

**Default:** Auto-detected

Set to `TRUE` if group membership uses UIDs instead of full DNs.

**Values:**
- `TRUE` - memberUid stores usernames
- `FALSE` - member stores full DNs

### `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES`

Add extra objectClasses when creating user accounts. Comma-separated list.

**Example:** `mailAccount,customUser`

See [Advanced Topics](advanced.md#extra-objectclasses) for more details.

### `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES`

Add extra attributes when creating user accounts. JSON format.

**Example:**
```bash
-e LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES='{"loginShell":"shell","homeDirectory":"text"}'
```

See [Advanced Topics](advanced.md#custom-attributes) for more details.

### `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES`

Add extra objectClasses when creating groups. Comma-separated list.

### `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`

Add extra attributes when creating groups. JSON format.

---

## User Account Settings

### `DEFAULT_USER_GROUP`

**Default:** `everybody`

The default group that new users are added to.

**Note:** This group will be created during setup if it doesn't exist.

### `DEFAULT_USER_SHELL`

**Default:** `/bin/bash`

The default login shell assigned to new user accounts.

**Common values:**
- `/bin/bash`
- `/bin/sh`
- `/bin/zsh`
- `/usr/sbin/nologin` - For accounts that shouldn't have shell access

### `ENFORCE_SAFE_SYSTEM_NAMES`

**Default:** `TRUE`

When enabled, usernames and group names must be valid POSIX account names (lowercase letters, numbers, hyphens, underscores, starting with a letter).

**Set to `FALSE`** to allow more flexible naming (e.g., uppercase letters, spaces).

### `USERNAME_FORMAT`

**Default:** `{first_name}-{last_name}`

Template for auto-generating usernames from user details.

**Available placeholders:**
- `{first_name}` - User's first name
- `{last_name}` - User's last name
- `{email_address}` - Email address

**Examples:**
- `{first_name}.{last_name}` - john.smith
- `{first_name}{last_name}` - johnsmith
- `{last_name}` - smith

### `USERNAME_REGEX`

**Default:** `^[a-z][a-zA-Z0-9\._-]{3,32}$`

Regular expression for validating usernames. Only used when `ENFORCE_SAFE_SYSTEM_NAMES=TRUE`.

### `PASSWORD_HASH`

**Default:** `SSHA`

The hashing algorithm used for passwords stored in LDAP.

**Supported values:**
- `SSHA` - Salted SHA-1 (recommended, widely compatible)
- `SHA` - SHA-1 (less secure, no salt)
- `MD5` - MD5 (deprecated)
- `SSHA256` - Salted SHA-256 (more secure, may not work with all LDAP servers)
- `SSHA512` - Salted SHA-512 (most secure, may not work with all LDAP servers)

### `ACCEPT_WEAK_PASSWORDS`

**Default:** `FALSE`

Set to `TRUE` to allow weak passwords that don't meet the strength requirements.

**Security Warning:** Only use for testing. Always require strong passwords in production.

### `SHOW_POSIX_ATTRIBUTES`

**Default:** `FALSE`

Set to `TRUE` to show POSIX attributes (UID, GID, home directory, shell) in the user interface.

**Usage:** Useful for administrators who need to see or modify these technical details.

---

## Multi-Factor Authentication

MFA configuration options. See [MFA Documentation](mfa.md) for detailed setup instructions.

### `MFA_ENABLED`

**Default:** `FALSE`

Set to `TRUE` to enable multi-factor authentication features.

**Requirements:** The [TOTP schema](https://github.com/wheelybird/ldap-totp-schema) must be installed in your LDAP directory.

### `MFA_REQUIRED_GROUPS`

**Default:** None

Comma-separated list of groups that require MFA. Users in these groups must enrol in MFA to access systems.

**Example:** `admins,developers,finance`

### `MFA_GRACE_PERIOD_DAYS`

**Default:** `7`

Number of days users have to enrol in MFA after being added to an MFA-required group. During this period they can still access systems.

**Example:** `14` - Give users 14 days to set up MFA

### `MFA_TOTP_ISSUER`

**Default:** `$ORGANISATION_NAME`

The issuer name displayed in authenticator apps when users scan the QR code.

**Example:** `Example Ltd VPN`

### `TOTP_SECRET_ATTRIBUTE`

**Default:** `totpSecret`

LDAP attribute name for storing TOTP secrets. Only change if using a custom schema.

### `TOTP_STATUS_ATTRIBUTE`

**Default:** `totpStatus`

LDAP attribute name for storing MFA status (none/pending/active/disabled).

### `TOTP_ENROLLED_DATE_ATTRIBUTE`

**Default:** `totpEnrolledDate`

LDAP attribute name for storing MFA enrolment date.

### `TOTP_SCRATCH_CODES_ATTRIBUTE`

**Default:** `totpScratchCode`

LDAP attribute name for storing backup codes.

### `TOTP_OBJECTCLASS`

**Default:** `totpUser`

LDAP objectClass name for users with MFA. Only change if using a custom schema.

---

## Email Settings

Configuration for sending email notifications. See [Advanced Topics](advanced.md#sending-emails) for setup details.

### `SMTP_HOSTNAME`

The hostname of your SMTP server.

**Example:** `smtp.gmail.com`

**Note:** Email features are disabled if this is not set.

### `SMTP_HOST_PORT`

**Default:** `25`

The port your SMTP server listens on.

**Common values:**
- `25` - Standard SMTP
- `587` - SMTP with StartTLS (recommended)
- `465` - SMTPS (SMTP over SSL)

### `SMTP_USERNAME`

Username for SMTP authentication. Leave unset if your SMTP server doesn't require authentication.

### `SMTP_PASSWORD`

Password for SMTP authentication.

**Security Note:** Consider using `SMTP_PASSWORD_FILE` with Docker secrets.

### `SMTP_USE_TLS`

**Default:** `FALSE`

Set to `TRUE` to use StartTLS encryption.

**Recommended** for security when using port 587.

### `SMTP_USE_SSL`

**Default:** `FALSE`

Set to `TRUE` to use SSL/TLS encryption.

**Usage:** Typically used with port 465.

**Note:** Don't set both `SMTP_USE_TLS` and `SMTP_USE_SSL` to TRUE.

### `SMTP_HELO_HOST`

The hostname to use in the SMTP HELO command. Usually auto-detected.

### `EMAIL_DOMAIN`

The domain for auto-generating email addresses when creating accounts.

**Example:** `example.com`

**Usage:** If set and a user's email address is blank, it will be generated as `username@example.com`.

### `EMAIL_FROM_ADDRESS`

**Default:** `admin@$EMAIL_DOMAIN`

The "From" address for emails sent by the system.

**Example:** `noreply@example.com`

### `EMAIL_FROM_NAME`

**Default:** `$SITE_NAME`

The "From" name for emails sent by the system.

**Example:** `Example Ltd User Management`

---

## Account Request Settings

Allow users to request accounts via a web form.

### `ACCOUNT_REQUESTS_ENABLED`

**Default:** `FALSE`

Set to `TRUE` to enable the account request form.

**Requirements:** Email must be configured (`SMTP_HOSTNAME` must be set).

### `ACCOUNT_REQUESTS_EMAIL`

**Default:** `$EMAIL_FROM_ADDRESS`

The email address where account requests are sent.

**Example:** `admins@example.com`

---

## Appearance and Behaviour

### `ORGANISATION_NAME`

**Default:** `LDAP`

Your organisation's name, displayed throughout the interface.

**Example:** `Example Ltd`

### `SITE_NAME`

**Default:** `$ORGANISATION_NAME user manager`

The name of the website, shown in the page title and header.

**Example:** `Example Ltd Account Manager`

### `SITE_LOGIN_LDAP_ATTRIBUTE`

**Default:** `$LDAP_ACCOUNT_ATTRIBUTE`

The LDAP attribute users enter when logging in.

**Usage:** Typically the same as `LDAP_ACCOUNT_ATTRIBUTE`, but can be different (e.g., allow login with email address).

### `SITE_LOGIN_FIELD_LABEL`

**Default:** `Username`

The label for the login field on the login page.

**Examples:**
- `Email Address` - If users log in with email
- `Employee ID` - If using employee IDs

### `SESSION_TIMEOUT`

**Default:** `10`

Number of minutes of inactivity before users are automatically logged out.

**Example:** `30` - 30-minute timeout

### `CUSTOM_LOGO`

Path to a custom logo image to replace the default logo.

**Usage:** Mount your logo file and set this to the path inside the container.

**Example:**
```bash
-v /path/to/logo.png:/custom/logo.png \
-e CUSTOM_LOGO=/custom/logo.png
```

### `CUSTOM_STYLES`

Path to a custom CSS file for additional styling.

**Example:**
```bash
-v /path/to/custom.css:/custom/styles.css \
-e CUSTOM_STYLES=/custom/styles.css
```

### `REMOTE_HTTP_HEADERS_LOGIN`

**Default:** `FALSE`

Set to `TRUE` to enable authentication via HTTP headers (e.g., when behind an authenticating reverse proxy).

**Security Warning:** Only use when the proxy is properly configured to set authentication headers.

---

## Debugging

### `LDAP_DEBUG`

**Default:** `FALSE`

Set to `TRUE` to enable verbose LDAP debugging output.

**Usage:** Helpful when troubleshooting LDAP connection or search issues. Logs will show detailed LDAP operations.

### `LDAP_VERBOSE_CONNECTION_LOGS`

**Default:** `FALSE`

Set to `TRUE` for even more detailed LDAP connection logging.

### `SESSION_DEBUG`

**Default:** `FALSE`

Set to `TRUE` to enable PHP session debugging.

### `SMTP_LOG_LEVEL`

**Default:** `0` (no logging)

SMTP debug logging level.

**Values:**
- `0` - No output
- `1` - Client commands
- `2` - Client commands and responses
- `3` - Connection and authentication details
- `4` - Low-level data output

---

## Using Docker Secrets

For sensitive configuration values like passwords, you can use Docker secrets or file-based configuration:

```bash
echo "my-ldap-password" | docker secret create ldap_admin_pwd -

docker service create \
  --secret ldap_admin_pwd \
  -e LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_pwd \
  wheelybird/ldap-user-manager
```

**Any environment variable can use the `_FILE` suffix** to read from a file instead of being set directly.
