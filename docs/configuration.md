# Configuration Reference

This document provides a comprehensive reference for all configuration options in Luminary.

**Note:** This documentation is auto-generated from the configuration registry.
To update this file, run: `php docs/generate_config_docs.php`

## Table of Contents
- [LDAP settings](#ldap-settings)
- [User account defaults](#user-account-defaults)
- [Multi-factor authentication](#multi-factor-authentication)
- [User profile settings](#user-profile-settings)
- [Email settings](#email-settings)
- [Interface & branding](#interface-&-branding)
- [Session & security](#session-&-security)
- [Audit logging 🔧](#audit-logging)
- [Password policy 🔧](#password-policy)
- [Account lifecycle 🔧](#account-lifecycle)
- [Debug & logging](#debug-&-logging)

---

## Legend

- 🔧 = Optional feature (disabled by default)
- ⚠️ = Mandatory configuration (must be set)
- 📝 = Has default value
- 🔢 = Array/list value
- ✅ = Boolean value

---

## LDAP settings

Connection settings and directory structure configuration

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| LDAP server URI<br><small>Format: ldap://hostname:port or ldaps://hostname:port</small> | string | ⚠️ *Required* | `LDAP_URI` | LDAP server URI |
| LDAP base distinguished name<br><small>Example: dc=example,dc=com</small> | string | ⚠️ *Required* | `LDAP_BASE_DN` | LDAP base distinguished name |
| Admin bind DN for LDAP operations<br><small>Full DN of admin account with write permissions</small> | string | ⚠️ *Required* | `LDAP_ADMIN_BIND_DN` | Admin bind DN for LDAP operations |
| Admin bind password<br><small>Password for admin bind DN</small> | string | ⚠️ *Required* | `LDAP_ADMIN_BIND_PWD` | Admin bind password |
| Organisational unit for user accounts<br><small>OU name only (without base DN)</small> | string | 📝 `people` | `LDAP_USER_OU` | Organisational unit for user accounts |
| Organisational unit for groups<br><small>OU name only (without base DN)</small> | string | 📝 `groups` | `LDAP_GROUP_OU` | Organisational unit for groups |
| Group name for administrators<br><small>Members of this group have admin access to Luminary</small> | string | 📝 `admins` | `LDAP_ADMINS_GROUP` | Group name for administrators |
| Attribute used for user account identifier<br><small>Typically uid or cn</small> | string | 📝 `uid` | `LDAP_ACCOUNT_ATTRIBUTE` | Attribute used for user account identifier |
| Attribute used for group identifier<br><small>Typically cn</small> | string | 📝 `cn` | `LDAP_GROUP_ATTRIBUTE` | Attribute used for group identifier |
| Require StartTLS for LDAP connections<br><small>Encrypts connection to LDAP server</small> | ✅ boolean | `FALSE` | `LDAP_REQUIRE_STARTTLS` | Require StartTLS for LDAP connections |
| Ignore TLS certificate validation errors<br><small>WARNING: Only use for development/testing</small> | ✅ boolean | `FALSE` | `LDAP_IGNORE_CERT_ERRORS` | Ignore TLS certificate validation errors |
| Force RFC2307bis schema<br><small>Use groupOfNames instead of posixGroup</small> | ✅ boolean | `FALSE` | `FORCE_RFC2307BIS` | Force RFC2307bis schema |

### Details

#### LDAP server URI

Format: ldap://hostname:port or ldaps://hostname:port

**Environment Variable:** `LDAP_URI`

---

#### LDAP base distinguished name

Example: dc=example,dc=com

**Environment Variable:** `LDAP_BASE_DN`

---

#### Admin bind DN for LDAP operations

Full DN of admin account with write permissions

**Environment Variable:** `LDAP_ADMIN_BIND_DN`

---

#### Admin bind password

Password for admin bind DN

**Environment Variable:** `LDAP_ADMIN_BIND_PWD`

---

#### Organisational unit for user accounts

OU name only (without base DN)

**Environment Variable:** `LDAP_USER_OU`

**Default:** `people`

---

#### Organisational unit for groups

OU name only (without base DN)

**Environment Variable:** `LDAP_GROUP_OU`

**Default:** `groups`

---

#### Group name for administrators

Members of this group have admin access to Luminary

**Environment Variable:** `LDAP_ADMINS_GROUP`

**Default:** `admins`

---

#### Attribute used for user account identifier

Typically uid or cn

**Environment Variable:** `LDAP_ACCOUNT_ATTRIBUTE`

**Default:** `uid`

---

#### Attribute used for group identifier

Typically cn

**Environment Variable:** `LDAP_GROUP_ATTRIBUTE`

**Default:** `cn`

---

#### Require StartTLS for LDAP connections

Encrypts connection to LDAP server

**Environment Variable:** `LDAP_REQUIRE_STARTTLS`

**Default:** `FALSE`

---

#### Ignore TLS certificate validation errors

WARNING: Only use for development/testing

**Environment Variable:** `LDAP_IGNORE_CERT_ERRORS`

**Default:** `FALSE`

---

#### Force RFC2307bis schema

Use groupOfNames instead of posixGroup

**Environment Variable:** `FORCE_RFC2307BIS`

**Default:** `FALSE`

---


## User account defaults

Default values and behaviour for new user accounts

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Default primary group for new users<br><small>Group name that new users will be added to</small> | string | 📝 `everybody` | `DEFAULT_USER_GROUP` | Default primary group for new users |
| Default login shell for new users<br><small>Full path to shell binary</small> | string | 📝 `/bin/bash` | `DEFAULT_USER_SHELL` | Default login shell for new users |
| Username format template<br><small>Template variables: {first_name}, {last_name}, {first_name_initial}, {last_name_initial}</small> | string | 📝 `{first_name}-{last_name}` | `USERNAME_FORMAT` | Username format template |
| Regular expression for username validation<br><small>Usernames must match this pattern</small> | string | 📝 `^[\p{L}\p{N}_.-]{2,64}$` | `USERNAME_REGEX` | Regular expression for username validation |
| Enforce username validation rules<br><small>Validate usernames against USERNAME_REGEX</small> | ✅ boolean | `TRUE` | `ENFORCE_USERNAME_VALIDATION` | Enforce username validation rules |
| Allow weak passwords<br><small>Skip password strength requirement (not recommended)</small> | ✅ boolean | `FALSE` | `ACCEPT_WEAK_PASSWORDS` | Allow weak passwords |
| Show POSIX attributes in forms<br><small>Display UID, GID, home directory, shell fields</small> | ✅ boolean | `FALSE` | `SHOW_POSIX_ATTRIBUTES` | Show POSIX attributes in forms |
| Password hash algorithm<br><small>Options: SHA, SSHA, SHA256, SHA512, ARGON2, etc.</small> | string | *Not set* | `PASSWORD_HASH` | Password hash algorithm |
| Email domain for auto-generation<br><small>Domain used when auto-generating user email addresses</small> | string | *Not set* | `EMAIL_DOMAIN` | Email domain for auto-generation |

### Details

#### Default primary group for new users

Group name that new users will be added to

**Environment Variable:** `DEFAULT_USER_GROUP`

**Default:** `everybody`

---

#### Default login shell for new users

Full path to shell binary

**Environment Variable:** `DEFAULT_USER_SHELL`

**Default:** `/bin/bash`

---

#### Username format template

Template variables: {first_name}, {last_name}, {first_name_initial}, {last_name_initial}

**Environment Variable:** `USERNAME_FORMAT`

**Default:** `{first_name}-{last_name}`

---

#### Regular expression for username validation

Usernames must match this pattern

**Environment Variable:** `USERNAME_REGEX`

**Default:** `^[\p{L}\p{N}_.-]{2,64}$`

---

#### Enforce username validation rules

Validate usernames against USERNAME_REGEX

**Environment Variable:** `ENFORCE_USERNAME_VALIDATION`

**Default:** `TRUE`

---

#### Allow weak passwords

Skip password strength requirement (not recommended)

**Environment Variable:** `ACCEPT_WEAK_PASSWORDS`

**Default:** `FALSE`

---

#### Show POSIX attributes in forms

Display UID, GID, home directory, shell fields

**Environment Variable:** `SHOW_POSIX_ATTRIBUTES`

**Default:** `FALSE`

---

#### Password hash algorithm

Options: SHA, SSHA, SHA256, SHA512, ARGON2, etc.

**Environment Variable:** `PASSWORD_HASH`

---

#### Email domain for auto-generation

Domain used when auto-generating user email addresses

**Environment Variable:** `EMAIL_DOMAIN`

---


## Multi-factor authentication

TOTP/MFA configuration and enforcement policies

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Enable MFA management features<br><small>Allows creating and managing MFA settings in LDAP. Requires TOTP schema to be installed.</small> | ✅ boolean | `FALSE` | `MFA_FEATURE_ENABLED` | Enable MFA management features |
| Groups that require MFA enrolment<br><small>Comma-separated list of group names</small> | 🔢 array | `[]` (empty) | `MFA_REQUIRED_GROUPS` | Groups that require MFA enrolment |
| Grace period for MFA enrolment<br><small>Days users have to set up MFA after being added to required group</small> | integer | 📝 `7` | `MFA_GRACE_PERIOD_DAYS` | Grace period for MFA enrolment |
| TOTP issuer name<br><small>Displayed in authenticator apps (e.g., "Example Ltd")</small> | string | 📝 `Luminary` | `MFA_TOTP_ISSUER` | TOTP issuer name |
| LDAP attribute for TOTP secret<br><small>Only change if using custom schema</small> | string | 📝 `totpSecret` | `TOTP_SECRET_ATTRIBUTE` | LDAP attribute for TOTP secret |
| LDAP attribute for MFA status<br><small>Values: none, pending, active, disabled</small> | string | 📝 `totpStatus` | `TOTP_STATUS_ATTRIBUTE` | LDAP attribute for MFA status |
| LDAP attribute for enrolment date<br><small>Used for grace period calculation</small> | string | 📝 `totpEnrolledDate` | `TOTP_ENROLLED_DATE_ATTRIBUTE` | LDAP attribute for enrolment date |
| LDAP attribute for backup codes<br><small>Multi-valued attribute for recovery codes</small> | string | 📝 `totpScratchCode` | `TOTP_SCRATCH_CODES_ATTRIBUTE` | LDAP attribute for backup codes |
| LDAP objectClass for MFA users<br><small>Only change if using custom schema</small> | string | 📝 `totpUser` | `TOTP_OBJECTCLASS` | LDAP objectClass for MFA users |
| LDAP objectClass for groups with MFA policies<br><small>Auxiliary object class for groups. Use this if not using ldap-totp-schema.</small> | string | 📝 `mfaGroup` | `GROUP_MFA_OBJECTCLASS` | LDAP objectClass for groups with MFA policies |
| LDAP attribute for group MFA requirement flag<br><small>Boolean attribute (TRUE/FALSE) indicating if group requires MFA</small> | string | 📝 `mfaRequired` | `GROUP_MFA_REQUIRED_ATTRIBUTE` | LDAP attribute for group MFA requirement flag |
| LDAP attribute for group MFA grace period<br><small>Integer attribute for grace period in days</small> | string | 📝 `mfaGracePeriodDays` | `GROUP_MFA_GRACE_PERIOD_ATTRIBUTE` | LDAP attribute for group MFA grace period |

### Details

#### Enable MFA management features

Allows creating and managing MFA settings in LDAP. Requires TOTP schema to be installed.

**Environment Variable:** `MFA_FEATURE_ENABLED`

**Default:** `FALSE`

---

#### Groups that require MFA enrolment

Comma-separated list of group names

**Environment Variable:** `MFA_REQUIRED_GROUPS`

**Default:** ``

---

#### Grace period for MFA enrolment

Days users have to set up MFA after being added to required group

**Environment Variable:** `MFA_GRACE_PERIOD_DAYS`

**Default:** `7`

---

#### TOTP issuer name

Displayed in authenticator apps (e.g., "Example Ltd")

**Environment Variable:** `MFA_TOTP_ISSUER`

**Default:** `Luminary`

---

#### LDAP attribute for TOTP secret

Only change if using custom schema

**Environment Variable:** `TOTP_SECRET_ATTRIBUTE`

**Default:** `totpSecret`

---

#### LDAP attribute for MFA status

Values: none, pending, active, disabled

**Environment Variable:** `TOTP_STATUS_ATTRIBUTE`

**Default:** `totpStatus`

---

#### LDAP attribute for enrolment date

Used for grace period calculation

**Environment Variable:** `TOTP_ENROLLED_DATE_ATTRIBUTE`

**Default:** `totpEnrolledDate`

---

#### LDAP attribute for backup codes

Multi-valued attribute for recovery codes

**Environment Variable:** `TOTP_SCRATCH_CODES_ATTRIBUTE`

**Default:** `totpScratchCode`

---

#### LDAP objectClass for MFA users

Only change if using custom schema

**Environment Variable:** `TOTP_OBJECTCLASS`

**Default:** `totpUser`

---

#### LDAP objectClass for groups with MFA policies

Auxiliary object class for groups. Use this if not using ldap-totp-schema.

**Environment Variable:** `GROUP_MFA_OBJECTCLASS`

**Default:** `mfaGroup`

---

#### LDAP attribute for group MFA requirement flag

Boolean attribute (TRUE/FALSE) indicating if group requires MFA

**Environment Variable:** `GROUP_MFA_REQUIRED_ATTRIBUTE`

**Default:** `mfaRequired`

---

#### LDAP attribute for group MFA grace period

Integer attribute for grace period in days

**Environment Variable:** `GROUP_MFA_GRACE_PERIOD_ATTRIBUTE`

**Default:** `mfaGracePeriodDays`

---


## User profile settings

Self-service user profile and editable attributes

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Built-in user-editable attributes<br><small>Default set of attributes users can safely edit</small> | 🔢 array | `telephonenumber`, `mobile`, `displayname`, ... | - | Built-in user-editable attributes |
| Additional user-editable attributes<br><small>Comma-separated list of LDAP attributes users can edit</small> | 🔢 array | `[]` (empty) | `USER_EDITABLE_ATTRIBUTES` | Additional user-editable attributes |
| Security blacklist of non-editable attributes<br><small>Attributes that users must NEVER be allowed to edit</small> | 🔢 array | `dn`, `uid`, `cn`, ... | - | Security blacklist of non-editable attributes |

### Details

#### Built-in user-editable attributes

Default set of attributes users can safely edit

**Default:** `telephonenumber, mobile, displayname, description, title, jpegphoto, sshpublickey`

---

#### Additional user-editable attributes

Comma-separated list of LDAP attributes users can edit

**Environment Variable:** `USER_EDITABLE_ATTRIBUTES`

**Default:** ``

---

#### Security blacklist of non-editable attributes

Attributes that users must NEVER be allowed to edit

**Default:** `dn, uid, cn, objectclass, uidnumber, gidnumber, homedirectory, loginshell, userpassword, sambantpassword, sambapassword, memberof, member, memberuid, uniquemember, totpsecret, totpstatus, totpenrolleddate, totpscratchcode, creatorsname, createtimestamp, modifiersname, modifytimestamp, entrydn, entryuuid, structuralobjectclass, hassubordinates, subschemasubentry`

---


## Email settings

SMTP configuration and email notifications

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| SMTP server hostname<br><small>Email features disabled if not set</small> | string | *Not set* | `SMTP_HOSTNAME` | SMTP server hostname |
| SMTP server port<br><small>Common: 25 (SMTP), 587 (StartTLS), 465 (SSL)</small> | integer | 📝 `25` | `SMTP_HOST_PORT` | SMTP server port |
| SMTP authentication username<br><small>Leave unset if no authentication required</small> | string | *Not set* | `SMTP_USERNAME` | SMTP authentication username |
| SMTP authentication password<br><small>Consider using SMTP_PASSWORD_FILE with Docker secrets</small> | string | *Not set* | `SMTP_PASSWORD` | SMTP authentication password |
| Use StartTLS for SMTP<br><small>Recommended for port 587</small> | ✅ boolean | `FALSE` | `SMTP_USE_TLS` | Use StartTLS for SMTP |
| Use SSL for SMTP<br><small>For port 465 (mutually exclusive with TLS)</small> | ✅ boolean | `FALSE` | `SMTP_USE_SSL` | Use SSL for SMTP |
| SMTP HELO hostname<br><small>Hostname to use in HELO/EHLO command</small> | string | *Not set* | `SMTP_HELO_HOST` | SMTP HELO hostname |
| From email address<br><small>Email address for outgoing messages</small> | string | 📝 `admin@luminary.id` | `EMAIL_FROM_ADDRESS` | From email address |
| From name for emails<br><small>Display name for outgoing messages</small> | string | 📝 `Luminary` | `EMAIL_FROM_NAME` | From name for emails |
| Reply-to email address<br><small>Email address for reply-to header (optional)</small> | string | *Not set* | `EMAIL_REPLY_TO_ADDRESS` | Reply-to email address |
| Email user on password change<br><small>Send notification email to user when their password is changed (by them or admin). Does not include the password.</small> | ✅ boolean | `FALSE` | `EMAIL_USER_ON_PASSWORD_CHANGE` | Email user on password change |
| Email admin when user changes password<br><small>Send notification to admin email when a user changes their own password (requires ADMIN_EMAIL to be set)</small> | ✅ boolean | `FALSE` | `EMAIL_ADMIN_ON_USER_PASSWORD_CHANGE` | Email admin when user changes password |
| Administrator email address<br><small>Email address for admin notifications (password changes, security alerts, etc.)</small> | string | *Not set* | `ADMIN_EMAIL` | Administrator email address |
| Enable account request feature<br><small>Requires SMTP to be configured</small> | ✅ boolean | `FALSE` | `ACCOUNT_REQUESTS_ENABLED` | Enable account request feature |
| Email for account requests<br><small>Where account request notifications are sent. Falls back to ADMIN_EMAIL if not set.</small> | string | *Not set* | `ACCOUNT_REQUESTS_EMAIL` | Email for account requests |

### Details

#### SMTP server hostname

Email features disabled if not set

**Environment Variable:** `SMTP_HOSTNAME`

---

#### SMTP server port

Common: 25 (SMTP), 587 (StartTLS), 465 (SSL)

**Environment Variable:** `SMTP_HOST_PORT`

**Default:** `25`

---

#### SMTP authentication username

Leave unset if no authentication required

**Environment Variable:** `SMTP_USERNAME`

---

#### SMTP authentication password

Consider using SMTP_PASSWORD_FILE with Docker secrets

**Environment Variable:** `SMTP_PASSWORD`

---

#### Use StartTLS for SMTP

Recommended for port 587

**Environment Variable:** `SMTP_USE_TLS`

**Default:** `FALSE`

---

#### Use SSL for SMTP

For port 465 (mutually exclusive with TLS)

**Environment Variable:** `SMTP_USE_SSL`

**Default:** `FALSE`

---

#### SMTP HELO hostname

Hostname to use in HELO/EHLO command

**Environment Variable:** `SMTP_HELO_HOST`

---

#### From email address

Email address for outgoing messages

**Environment Variable:** `EMAIL_FROM_ADDRESS`

**Default:** `admin@luminary.id`

---

#### From name for emails

Display name for outgoing messages

**Environment Variable:** `EMAIL_FROM_NAME`

**Default:** `Luminary`

---

#### Reply-to email address

Email address for reply-to header (optional)

**Environment Variable:** `EMAIL_REPLY_TO_ADDRESS`

---

#### Email user on password change

Send notification email to user when their password is changed (by them or admin). Does not include the password.

**Environment Variable:** `EMAIL_USER_ON_PASSWORD_CHANGE`

**Default:** `FALSE`

---

#### Email admin when user changes password

Send notification to admin email when a user changes their own password (requires ADMIN_EMAIL to be set)

**Environment Variable:** `EMAIL_ADMIN_ON_USER_PASSWORD_CHANGE`

**Default:** `FALSE`

---

#### Administrator email address

Email address for admin notifications (password changes, security alerts, etc.)

**Environment Variable:** `ADMIN_EMAIL`

---

#### Enable account request feature

Requires SMTP to be configured

**Environment Variable:** `ACCOUNT_REQUESTS_ENABLED`

**Default:** `FALSE`

---

#### Email for account requests

Where account request notifications are sent. Falls back to ADMIN_EMAIL if not set.

**Environment Variable:** `ACCOUNT_REQUESTS_EMAIL`

---


## Interface & branding

Customisation, branding, and user interface settings

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Organisation name<br><small>Displayed throughout the interface</small> | string | 📝 `Luminary` | `ORGANISATION_NAME` | Organisation name |
| Site name<br><small>Displayed in page titles and navigation</small> | string | 📝 `Luminary` | `SITE_NAME` | Site name |
| Server hostname<br><small>Hostname used in URLs</small> | string | 📝 `luminary.id` | `SERVER_HOSTNAME` | Server hostname |
| Server path<br><small>Base path for the application (e.g., /luminary/)</small> | string | 📝 `/` | `SERVER_PATH` | Server path |
| Login field label<br><small>Label for login form username field</small> | string | 📝 `Username` | `SITE_LOGIN_FIELD_LABEL` | Login field label |
| LDAP attribute for login<br><small>Which attribute to use for login authentication</small> | string | 📝 `uid` | `SITE_LOGIN_LDAP_ATTRIBUTE` | LDAP attribute for login |
| Custom logo path<br><small>Path to custom logo file</small> | string | `FALSE` | `CUSTOM_LOGO` | Custom logo path |
| Custom CSS path<br><small>Path to custom stylesheet</small> | string | `FALSE` | `CUSTOM_STYLES` | Custom CSS path |
| Items per page for listing pages<br><small>Number of users/groups to show per page in account_manager lists</small> | integer | 📝 `50` | `PAGINATION_ITEMS_PER_PAGE` | Items per page for listing pages |

### Details

#### Organisation name

Displayed throughout the interface

**Environment Variable:** `ORGANISATION_NAME`

**Default:** `Luminary`

---

#### Site name

Displayed in page titles and navigation

**Environment Variable:** `SITE_NAME`

**Default:** `Luminary`

---

#### Server hostname

Hostname used in URLs

**Environment Variable:** `SERVER_HOSTNAME`

**Default:** `luminary.id`

---

#### Server path

Base path for the application (e.g., /luminary/)

**Environment Variable:** `SERVER_PATH`

**Default:** `/`

---

#### Login field label

Label for login form username field

**Environment Variable:** `SITE_LOGIN_FIELD_LABEL`

**Default:** `Username`

---

#### LDAP attribute for login

Which attribute to use for login authentication

**Environment Variable:** `SITE_LOGIN_LDAP_ATTRIBUTE`

**Default:** `uid`

---

#### Custom logo path

Path to custom logo file

**Environment Variable:** `CUSTOM_LOGO`

**Default:** `FALSE`

---

#### Custom CSS path

Path to custom stylesheet

**Environment Variable:** `CUSTOM_STYLES`

**Default:** `FALSE`

---

#### Items per page for listing pages

Number of users/groups to show per page in account_manager lists

**Environment Variable:** `PAGINATION_ITEMS_PER_PAGE`

**Default:** `50`

---


## Session & security

Session management and security settings

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Session timeout in minutes<br><small>Inactive sessions will be logged out</small> | integer | 📝 `10` | `SESSION_TIMEOUT` | Session timeout in minutes |
| Disable HTTPS redirect<br><small>WARNING: Only use for development/testing</small> | ✅ boolean | `FALSE` | `NO_HTTPS` | Disable HTTPS redirect |
| Enable HTTP header authentication<br><small>Login using HTTP headers (e.g., from reverse proxy)</small> | ✅ boolean | `FALSE` | `REMOTE_HTTP_HEADERS_LOGIN` | Enable HTTP header authentication |

### Details

#### Session timeout in minutes

Inactive sessions will be logged out

**Environment Variable:** `SESSION_TIMEOUT`

**Default:** `10`

---

#### Disable HTTPS redirect

WARNING: Only use for development/testing

**Environment Variable:** `NO_HTTPS`

**Default:** `FALSE`

---

#### Enable HTTP header authentication

Login using HTTP headers (e.g., from reverse proxy)

**Environment Variable:** `REMOTE_HTTP_HEADERS_LOGIN`

**Default:** `FALSE`

---


## Audit logging 🔧

Audit trail and activity logging configuration (optional)

> **Note:** This is an optional feature. Set `AUDIT_ENABLED=TRUE` to enable.

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Enable audit logging<br><small>Log all administrative actions to audit trail</small> | ✅ boolean | `FALSE` | `AUDIT_ENABLED` | Enable audit logging |
| Audit log destination<br><small>Use "stdout" for Docker (default), or full path to file for traditional deployments</small> | string | 📝 `stdout` | `AUDIT_LOG_FILE` | Audit log destination |
| Audit log retention period<br><small>Number of days to keep audit logs</small> | integer | 📝 `90` | `AUDIT_LOG_RETENTION_DAYS` | Audit log retention period |

### Details

#### Enable audit logging

Log all administrative actions to audit trail

**Environment Variable:** `AUDIT_ENABLED`

**Default:** `FALSE`

---

#### Audit log destination

Use "stdout" for Docker (default), or full path to file for traditional deployments

**Environment Variable:** `AUDIT_LOG_FILE`

**Default:** `stdout`

---

#### Audit log retention period

Number of days to keep audit logs

**Environment Variable:** `AUDIT_LOG_RETENTION_DAYS`

**Default:** `90`

---


## Password policy 🔧

Password complexity and expiration policies (optional)

> **Note:** This is an optional feature. Set `PASSWORD_POLICY_ENABLED=TRUE` to enable.

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Enable password policy enforcement<br><small>Server-side validation of password requirements. Complexity checks work without additional setup. History/expiry features require OpenLDAP ppolicy overlay.</small> | ✅ boolean | `FALSE` | `PASSWORD_POLICY_ENABLED` | Enable password policy enforcement |
| Enable OpenLDAP ppolicy overlay integration<br><small>When enabled, self-service password changes use Password Modify Extended Operation to allow ppolicy overlay to enforce password history and expiry. Requires STARTTLS or LDAPS to be enabled for security. Only applies to self-service password changes, not admin changes.</small> | ✅ boolean | `FALSE` | `PPOLICY_ENABLED` | Enable OpenLDAP ppolicy overlay integration |
| Minimum password length<br><small>Minimum number of characters required</small> | integer | 📝 `8` | `PASSWORD_MIN_LENGTH` | Minimum password length |
| Require uppercase letters<br><small>Password must contain at least one uppercase letter</small> | ✅ boolean | `TRUE` | `PASSWORD_REQUIRE_UPPERCASE` | Require uppercase letters |
| Require lowercase letters<br><small>Password must contain at least one lowercase letter</small> | ✅ boolean | `TRUE` | `PASSWORD_REQUIRE_LOWERCASE` | Require lowercase letters |
| Require numbers<br><small>Password must contain at least one number</small> | ✅ boolean | `TRUE` | `PASSWORD_REQUIRE_NUMBERS` | Require numbers |
| Require special characters<br><small>Password must contain at least one special character</small> | ✅ boolean | `FALSE` | `PASSWORD_REQUIRE_SPECIAL` | Require special characters |
| Minimum password strength score<br><small>Minimum score from 0-4 (from existing strength checker)</small> | integer | 📝 `3` | `PASSWORD_MIN_SCORE` | Minimum password strength score |
| Password history count<br><small>Number of previous passwords to check (0=disabled). REQUIRES: OpenLDAP ppolicy overlay</small> | integer | 📝 `0` | `PASSWORD_HISTORY_COUNT` | Password history count |
| Password expiry days<br><small>Days until password expires (0=never). REQUIRES: OpenLDAP ppolicy overlay</small> | integer | 📝 `0` | `PASSWORD_EXPIRY_DAYS` | Password expiry days |
| Password expiry warning period<br><small>Days before expiry to show warning</small> | integer | 📝 `7` | `PASSWORD_EXPIRY_WARNING_DAYS` | Password expiry warning period |

### Details

#### Enable password policy enforcement

Server-side validation of password requirements. Complexity checks work without additional setup. History/expiry features require OpenLDAP ppolicy overlay.

**Environment Variable:** `PASSWORD_POLICY_ENABLED`

**Default:** `FALSE`

---

#### Enable OpenLDAP ppolicy overlay integration

When enabled, self-service password changes use Password Modify Extended Operation to allow ppolicy overlay to enforce password history and expiry. Requires STARTTLS or LDAPS to be enabled for security. Only applies to self-service password changes, not admin changes.

**Environment Variable:** `PPOLICY_ENABLED`

**Default:** `FALSE`

---

#### Minimum password length

Minimum number of characters required

**Environment Variable:** `PASSWORD_MIN_LENGTH`

**Default:** `8`

---

#### Require uppercase letters

Password must contain at least one uppercase letter

**Environment Variable:** `PASSWORD_REQUIRE_UPPERCASE`

**Default:** `TRUE`

---

#### Require lowercase letters

Password must contain at least one lowercase letter

**Environment Variable:** `PASSWORD_REQUIRE_LOWERCASE`

**Default:** `TRUE`

---

#### Require numbers

Password must contain at least one number

**Environment Variable:** `PASSWORD_REQUIRE_NUMBERS`

**Default:** `TRUE`

---

#### Require special characters

Password must contain at least one special character

**Environment Variable:** `PASSWORD_REQUIRE_SPECIAL`

**Default:** `FALSE`

---

#### Minimum password strength score

Minimum score from 0-4 (from existing strength checker)

**Environment Variable:** `PASSWORD_MIN_SCORE`

**Default:** `3`

---

#### Password history count

Number of previous passwords to check (0=disabled). REQUIRES: OpenLDAP ppolicy overlay

**Environment Variable:** `PASSWORD_HISTORY_COUNT`

**Default:** `0`

---

#### Password expiry days

Days until password expires (0=never). REQUIRES: OpenLDAP ppolicy overlay

**Environment Variable:** `PASSWORD_EXPIRY_DAYS`

**Default:** `0`

---

#### Password expiry warning period

Days before expiry to show warning

**Environment Variable:** `PASSWORD_EXPIRY_WARNING_DAYS`

**Default:** `7`

---


## Account lifecycle 🔧

Account expiration and automated management (optional)

> **Note:** This is an optional feature. Set `LIFECYCLE_ENABLED=TRUE` to enable.

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Enable account lifecycle management<br><small>Enforces account expiration at login time (no background jobs required)</small> | ✅ boolean | `FALSE` | `LIFECYCLE_ENABLED` | Enable account lifecycle management |
| Enable account expiration<br><small>Automatically disable accounts after expiry date</small> | ✅ boolean | `FALSE` | `ACCOUNT_EXPIRY_ENABLED` | Enable account expiration |
| Account inactivity threshold<br><small>Days of inactivity before account is disabled</small> | integer | 📝 `90` | `ACCOUNT_INACTIVE_DAYS` | Account inactivity threshold |
| Account expiry warning period<br><small>Days before expiry to send warning email</small> | integer | 📝 `14` | `ACCOUNT_EXPIRY_WARNING_DAYS` | Account expiry warning period |
| Enable automatic account cleanup<br><small>Automatically delete expired accounts (use with caution)</small> | ✅ boolean | `FALSE` | `ACCOUNT_CLEANUP_ENABLED` | Enable automatic account cleanup |

### Details

#### Enable account lifecycle management

Enforces account expiration at login time (no background jobs required)

**Environment Variable:** `LIFECYCLE_ENABLED`

**Default:** `FALSE`

---

#### Enable account expiration

Automatically disable accounts after expiry date

**Environment Variable:** `ACCOUNT_EXPIRY_ENABLED`

**Default:** `FALSE`

---

#### Account inactivity threshold

Days of inactivity before account is disabled

**Environment Variable:** `ACCOUNT_INACTIVE_DAYS`

**Default:** `90`

---

#### Account expiry warning period

Days before expiry to send warning email

**Environment Variable:** `ACCOUNT_EXPIRY_WARNING_DAYS`

**Default:** `14`

---

#### Enable automatic account cleanup

Automatically delete expired accounts (use with caution)

**Environment Variable:** `ACCOUNT_CLEANUP_ENABLED`

**Default:** `FALSE`

---


## Debug & logging

Debug modes and verbose logging

| Configuration | Type | Default | Environment Variable | Description |
|--------------|------|---------|---------------------|-------------|
| Enable LDAP debug logging<br><small>WARNING: May expose sensitive information</small> | ✅ boolean | `FALSE` | `LDAP_DEBUG` | Enable LDAP debug logging |
| Verbose LDAP connection logs<br><small>Log all LDAP connection details</small> | ✅ boolean | `FALSE` | `LDAP_VERBOSE_CONNECTION_LOGS` | Verbose LDAP connection logs |
| Enable session debug logging<br><small>Log session management details</small> | ✅ boolean | `FALSE` | `SESSION_DEBUG` | Enable session debug logging |
| Show detailed error messages<br><small>Display full error details in browser (development only). Set to FALSE in production to show generic error pages.</small> | ✅ boolean | `FALSE` | `SHOW_ERROR_DETAILS` | Show detailed error messages |
| SMTP debug level<br><small>0=off, 1=client, 2=client+server, 3=verbose, 4=very verbose</small> | integer | 📝 `0` | `SMTP_LOG_LEVEL` | SMTP debug level |

### Details

#### Enable LDAP debug logging

WARNING: May expose sensitive information

**Environment Variable:** `LDAP_DEBUG`

**Default:** `FALSE`

---

#### Verbose LDAP connection logs

Log all LDAP connection details

**Environment Variable:** `LDAP_VERBOSE_CONNECTION_LOGS`

**Default:** `FALSE`

---

#### Enable session debug logging

Log session management details

**Environment Variable:** `SESSION_DEBUG`

**Default:** `FALSE`

---

#### Show detailed error messages

Display full error details in browser (development only). Set to FALSE in production to show generic error pages.

**Environment Variable:** `SHOW_ERROR_DETAILS`

**Default:** `FALSE`

---

#### SMTP debug level

0=off, 1=client, 2=client+server, 3=verbose, 4=very verbose

**Environment Variable:** `SMTP_LOG_LEVEL`

**Default:** `0`

---


## Environment Variable Summary

For a complete list of environment variables and their current values, log in as an administrator and navigate to **System Config** in the main menu.

## Configuration Best Practices

### Security

1. **Never commit sensitive values** to version control (e.g., `LDAP_ADMIN_BIND_PWD`, `SMTP_PASSWORD`)
2. **Use strong passwords** for admin bind DN and SMTP authentication
3. **Enable TLS/SSL** for LDAP and SMTP connections in production
4. **Restrict editable attributes** carefully to prevent privilege escalation
5. **Review the attribute blacklist** - these attributes should never be user-editable

### Performance

1. **Minimize debug logging** in production (disable `LDAP_DEBUG`, `SESSION_DEBUG`, `SMTP_LOG_LEVEL`)
2. **Use connection pooling** for LDAP when available
3. **Set appropriate session timeouts** based on your security requirements

### Maintenance

1. **Document custom configurations** - note why you changed from defaults
2. **Test configuration changes** in a development environment first
3. **Review the System Config page** regularly to identify drift from defaults
4. **Keep environment variables** in a secure configuration management system

## Adding New Configuration Options

To add new configuration options:

1. Open `www/includes/config_registry.inc.php`
2. Add your configuration to the appropriate category in `$CONFIG_REGISTRY`
3. Include all metadata: description, help, type, default, mandatory, env_var, variable
4. The System Config page and this documentation will auto-update

Example:

```php
'MY_NEW_CONFIG' => array(
  'category' => 'interface',
  'description' => 'My new configuration option',
  'help' => 'Detailed explanation of what this does and how to use it',
  'type' => 'string',
  'default' => 'default_value',
  'mandatory' => false,
  'env_var' => 'MY_NEW_CONFIG',
  'variable' => '$MY_NEW_CONFIG',
  'display_code' => false
),
```

## Getting Help

- Check the [System Config page](#) to see current values and defaults
- Review [GitHub Issues](https://github.com/wheelybird/ldap-user-manager/issues) for common questions
- Consult LDAP server documentation for LDAP-specific settings

---

*This documentation was automatically generated from the configuration registry.*
*Last updated: 2025-12-05 15:37:57 UTC*
