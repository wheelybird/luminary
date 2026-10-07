# Changelog

## [Unreleased]

### Fixed

- **[#275](https://github.com/wheelybird/luminary/issues/275)** - `ldapi://` connections are no longer treated as insecure. A Unix domain socket never leaves the host, but the connection code only exempted `ldaps://` from StartTLS, so an `ldapi://` URI was sent down the plain-LDAP path: StartTLS was attempted and failed, an error was logged, the "Insecure LDAP connection" banner appeared, and the connection was recorded as `plain`. That last part also blocked self-service password changes via the ppolicy overlay, which refuses to send a cleartext password over a connection it doesn't consider protected, and `LDAP_REQUIRE_STARTTLS=TRUE` made it fatal outright. A local socket is now recognised as its own connection type (`LDAPI`) and accepted as protected, since the socket's filesystem permissions are the security boundary. The setup checks and the System configuration page report it as a local socket rather than claiming it is encrypted.

## [2.2.1]

### Security

- **[#270](https://github.com/wheelybird/luminary/issues/270)** - A TOTP code can no longer be replayed within its validity window during login. Once a code has been accepted, it is remembered (hashed, per user) for the ~90-second window it would otherwise remain valid, and a second submission of the same code is rejected (RFC 6238 §5.2). The check lives in the login verify path only, so enrolment — which validates codes over AJAX and legitimately retries — is unaffected.
- **[#271](https://github.com/wheelybird/luminary/issues/271)** - The System configuration page no longer reveals `LDAP_ADMIN_BIND_PWD` or `SMTP_PASSWORD` in clear text. Values flagged `hide_value` in the registry are now masked, which also keeps them out of the row's `data-search` attribute in the page source. Unset credentials still show "(not set)" so you can tell whether one is configured.

### Fixed

- **[#268](https://github.com/wheelybird/luminary/issues/268)** - Saving a group's description (or any attribute) from the Attributes tab no longer removes the group's members. The Attributes tab now has its own handler (`group_attributes_handler.php`) and form marker, so attribute updates and membership changes are fully independent operations.
- **[#264](https://github.com/wheelybird/luminary/issues/264)** - Users can now add SSH keys on the self-service "My profile" page. The "+" button did nothing because the page was missing the JavaScript (`add_field_to()`) the admin user pages already include. Saving a key also failed with an "object class violation" because the user's entry lacked the `ldapPublicKey` object class the `sshPublicKey` attribute requires; the profile save now detects this generically — for any editable attribute the entry's current object classes don't permit, it looks up the auxiliary object class that provides the attribute (from the directory schema), adds it, and retries — rather than special-casing individual attributes.
- Login MFA page: the verification code input is no longer full-width — it is now capped to suit a 6-digit code, sitting alongside its label.
- Self-service profile: uploading a JPEG profile photo no longer crashes with "Call to undefined function `imagecreatefromjpeg()`". The Docker image now builds the GD extension with JPEG support (`--with-jpeg`), and the upload validation degrades gracefully (relying on the independent MIME-type check) when GD lacks JPEG support instead of throwing a fatal error.

- The **Password reset** configuration category was missing from the config registry's category list, so all of its settings (`PASSWORD_RESET_*` and `USE_LDAP_AS_DB`) were silently absent from the System Config page and the generated documentation. The category has been added, making these settings visible in both.

### Documentation

- **[#267](https://github.com/wheelybird/luminary/issues/267)** - Corrected the website customisation instructions. `CUSTOM_LOGO` and `CUSTOM_STYLES` are URL paths (emitted verbatim as `<img src>` / `<link href>`), so the referenced files must be mounted inside the document root (`/opt/luminary`) and the variable set to the matching URL — the previous `/custom/...` example mounted outside the document root and could not be served. Added a Docker Compose example and clarified the registry help text for both variables.

### Added

- **[#266](https://github.com/wheelybird/luminary/issues/266)** - New `PASSWORD_RESET_ALLOWED_HOSTS` setting. The password reset link normally always points at `SERVER_HOSTNAME`; for instances reachable under more than one name, this optional allowlist lets the link follow the actual request host when it matches an allowed hostname, falling back to `SERVER_HOSTNAME` otherwise. The allowlist is the security boundary — an unlisted request host is never used — which prevents password reset poisoning (CWE-640).
- **[#265](https://github.com/wheelybird/luminary/issues/265)** - `USERNAME_FORMAT` now supports `{first_name:N}` and `{last_name:N}` placeholders, which take the first N characters of a name (e.g. `{first_name:3}{last_name:5}` gives `johsmith` for John Smith). `{first_name_initial}` is the N=1 case, so existing formats are unchanged. The live username preview on the create-user form matches. If N exceeds the name length, the whole name is used.
- **[#272](https://github.com/wheelybird/luminary/issues/272)** - New `USER_PRIVATE_GROUPS` setting, which turns the user-private-group model into a deliberate mode: each new account gets a group named after it, taking the user's UID as its GID rather than the next value from `cn=lastGID`, and that group is removed again when the account is deleted. It is off by default, so existing installations are unaffected. Previously a per-user group was only created as a fallback for a missing `DEFAULT_USER_GROUP`, which meant creating that group later — as the setup page offers to — quietly put new accounts back into the shared group; with the mode on, `DEFAULT_USER_GROUP` is not used and the setup page no longer asks for it. `cn=lastGID` is not touched either, so the counter ordinary groups draw from only moves when one of those is added; because that leaves neither counter aware of the other, both sides skip a number some group already holds — an account takes the next UID that is also free as a GID, an ordinary group passes over a GID a private one has — so two groups can never share a GID, whatever ranges the IDs are drawn from. The check on the group side applies regardless of the setting, since private groups remain in the directory once it is switched off again; where there are none it cannot change the outcome. Because the group is created after the account rather than before it, it can also hold the user as a real member instead of the `cn=placeholder` entry the fallback left behind under RFC2307bis. Setting a GID explicitly on the create-user form still takes precedence.
- **[#269](https://github.com/wheelybird/luminary/issues/269)** - New `USER_EDITABLE_ATTRIBUTES_DISABLED` flag. When enabled, no attributes are user-editable regardless of `USER_EDITABLE_ATTRIBUTES`, and the "My profile" item is removed from the menu entirely (the page also remains read-only and enforces this server-side if reached directly). Because setting `USER_EDITABLE_ATTRIBUTES` to an empty value falls back to the built-in defaults, this flag is the way to disable profile editing entirely.

## [2.2.0] - 2026-07-13

### Security

- Session and setup-admin passkeys (`generate_passkey()`) are now generated with a cryptographically secure PRNG (`random_bytes()`) instead of `mt_rand()`
- Password salts (`generate_salt()`) are now generated with `random_int()`; removed a `mt_srand()` reseed that always evaluated to `mt_srand(0)`, which produced predictable salts and corrupted the shared PRNG state
- Account-request form: the proof-of-humanity check now fails closed when no CAPTCHA has been generated, uses a timing-safe comparison (`hash_equals()`), and consumes the token after each attempt to prevent reuse
- Account-request form: user-supplied values are now HTML-encoded in the admin notification email and URL-encoded in the account-creation link, and are HTML-encoded when reflected back into the form, preventing HTML/parameter injection and reflected XSS; control characters are also stripped from the email subject header as defence-in-depth against header injection
- The four issues above (weak PRNG for session/setup passkeys and password salts, the CAPTCHA bypass, and unsanitised account-request input) were reported privately by **red jh0n** — thank you for the responsible disclosure
- Session and setup cookie passkey comparisons now use `hash_equals()`
- Docker image: apply latest security patches at build time
- Docker image: remove build-time dev packages and kernel headers from final image, reducing HIGH CVE count from 46 to 17 (0 CRITICAL)
- Clean up PHPMailer archive from `/tmp` after extraction

### Fixed

- **[#261](https://github.com/wheelybird/luminary/pull/261)** - Fatal error ("Cannot redeclare `open_ldap_connection()`") on MFA and account-expired login paths caused by duplicate `ldap_functions.inc.php` includes
- **[#262](https://github.com/wheelybird/luminary/issues/262)** - `LDAP_TLS_CACERT` / `LDAP_TLS_CACERT_FILE` are now applied on first boot: the entrypoint appends the `TLS_CACERT` directive to `ldap.conf` when none exists (previously a `sed` replacement silently matched nothing, leaving the CA unconfigured)
- Corrected a string-concatenation typo (`+=` → `.=`) in setup-session debug logging
- **[#252](https://github.com/wheelybird/luminary/issues/252)** - Boolean configuration values (including `SMTP_USE_SSL`) now accept `TRUE`, `true`, `1>

### Added

- **[#260](https://github.com/wheelybird/luminary/issues/260)** - `USER_EDITABLE_ATTRIBUTES` now sets the definitive list of attributes users can edit in their profile (e.g. `USER_EDITABLE_ATTRIBUTES=jpegphoto`). If not set, sensible defaults are used.

### Changed

- **Breaking:** `USER_EDITABLE_ATTRIBUTES` now replaces the default list rather than adding to it. If you previously set this variable to add extra attributes, you will need to include the defaults you still want. The built-in defaults are: `telephonenumber,mobile,displayname,description,title,jpegphoto,sshpublickey`. For example, if you previously had `USER_EDITABLE_ATTRIBUTES=roomnumber` and want to keep the defaults, change it to `USER_EDITABLE_ATTRIBUTES=telephonenumber,mobile,displayname,description,title,jpegphoto,sshpublickey,roomnumber`.
- **[#254](https://github.com/wheelybird/luminary/issues/254)** - "Powered by Luminary" footer with link to GitHub project (disable with `SHOW_POWERED_BY=FALSE`)


## [2.1.1] - 2025-12-12

### Fixed

- **[#246](https://github.com/wheelybird/luminary/issues/246)** - Nested OU support: LDAP functions now search for user DNs instead of constructing them
- Environment variable `_FILE` suffix handling for Docker secrets now processed early in entrypoint
- Group creation UX: button text, attributes tab visibility during creation, MFA tab appearing after creation
- Form structure bugs causing multiple handlers to trigger on single form submission

## [2.1.0] - 2025-12-05

### Added

- Self-service password reset via email with token validation and rate limiting
- Optional LDAP storage for application data (sessions, tokens, rate limits) with filesystem fallback
- Automated daily cleanup of expired LDAP data via cron (randomised 3-5am)
- New password reset email events and templates

### Removed

- GROUP_BULK_OPERATIONS_ENABLED, GROUP_TEMPLATES_ENABLED, GROUP_NESTING_ENABLED config placeholders (unimplemented features that were accidentally included)

### Fixed

- **[#242](https://github.com/wheelybird/luminary/issues/242)** - Fatal error when creating initial admin account (undefined STDERR constant)
- **[#241](https://github.com/wheelybird/luminary/issues/241)** - Inconsistent password change email behaviour (checkbox now works correctly)
- **[#240](https://github.com/wheelybird/luminary/issues/240)** - SMTP_HOST_PORT variable maintained for backwards compatibility
- Object class preservation when updating users with MFA enabled (prevents `totpSecret` attribute errors)
- Duplicate group membership update banners (removed duplicate handler include)

## [2.0.0] - 2025-11-29

### Added

#### Multi-Factor Authentication (MFA/TOTP) System
**Complete MFA implementation integrated throughout Luminary**

- **Self-service MFA enrollment** (`/manage_mfa`)
  - QR code generation for authenticator apps (Google Authenticator, Authy, etc.)
  - Two-code verification during setup to ensure correct configuration
  - 10 emergency backup/scratch codes generated per user
  - Pure PHP TOTP implementation (RFC 6238 compliant)
  - No external dependencies

- **LDAP-backed secret storage**
  - TOTP secrets stored securely in LDAP user objects
  - Uses `totpSecret` attribute from ldap-totp-schema
  - Centralised management across multiple services
  - Supports custom TOTP attribute names via `MFA_TOTP_ATTRIBUTE`

- **Group-based MFA enforcement**
  - Configure MFA requirements per LDAP group
  - Groups can have `mfaRequired=TRUE` attribute
  - Per-group grace periods (`mfaGracePeriodDays` attribute)
  - Automatic `totpStatus=pending` when user added to MFA-required group
  - Shortest grace period wins when user in multiple groups
  - Configurable group MFA attributes for custom schemas

- **MFA-protected login for Luminary**
  - Two-step authentication: password validation, then TOTP code
  - Enforces MFA for users in MFA-required groups
  - Grace period support for new users
  - Automatic redirect to MFA setup if grace period expired

- **Admin MFA management** (`/account_manager/mfa_status.php`)
  - Dashboard showing MFA enrollment status for all users
  - Filter by enrollment status, group membership
  - Visual status indicators (active, pending, none, disabled)
  - Quick view of users without MFA in required groups
  - Grace period expiration tracking

- **MFA environment variables**
  - `MFA_FEATURE_ENABLED` - Enable/disable MFA features (default: FALSE)
  - `MFA_REQUIRED_GROUPS` - Comma-separated groups requiring MFA (config-based fallback)
  - `MFA_GRACE_PERIOD_DAYS` - Default grace period (default: 7)
  - `MFA_TOTP_ISSUER` - Name in authenticator app (default: "LDAP")
  - `MFA_TOTP_ATTRIBUTE` - LDAP attribute for TOTP secret (default: "totpSecret")
  - `MFA_GROUP_REQUIRED_ATTRIBUTE` - Group MFA requirement attribute (default: "mfaRequired")
  - `MFA_GROUP_GRACE_ATTRIBUTE` - Group grace period attribute (default: "mfaGracePeriodDays")

#### Configuration Registry System

  - Self-documenting configuration architecture
  - Auto-generated System Config page** (`/system_config`)
  - Auto-generated documentation

#### User Interface Enhancements

  - Bootstrap 5 migration
  - Unicode and internationalisation support
  - Navigation improvements
  - System information page

#### User Profile Module Refinements

  - Default attributes reduced to 7 essential fields
  - User-friendly field labels
  - JPEG photo validation (format + 500KB limit)
  - Security enhancements:
    - Blacklist enforcement for restricted attributes
    - Proper validation for all editable fields
    - Safe defaults for new attributes

#### Password Policy and Complexity Enforcement 🔒
**Comprehensive password policy support with ppolicy overlay integration**

- **ppolicy overlay detection**
  - Automatic detection of OpenLDAP ppolicy overlay availability
  - Graceful fallback when ppolicy not available
  - Checks for ppolicy control OID (1.3.6.1.4.1.42.2.27.8.5.1)

- **Password complexity validation**
  - Minimum length enforcement (configurable via `PASSWORD_MIN_LENGTH`)
  - Uppercase letter requirement (configurable via `PASSWORD_REQUIRE_UPPERCASE`)
  - Lowercase letter requirement (configurable via `PASSWORD_REQUIRE_LOWERCASE`)
  - Number requirement (configurable via `PASSWORD_REQUIRE_NUMBERS`)
  - Special character requirement (configurable via `PASSWORD_REQUIRE_SPECIAL`)
  - Minimum strength score (configurable via `PASSWORD_MIN_SCORE`)

- **Current password verification**
  - Requires current password when changing password (when ppolicy enabled)
  - Prevents unauthorised password changes from open sessions
  - Uses ppolicy-aware password change operations

- **Password history and expiry tracking** (requires ppolicy overlay)
  - Displays password changed date (`pwdChangedTime`)
  - Shows password age in days
  - Displays password expiry date when `PASSWORD_EXPIRY_DAYS` configured
  - Prevents password reuse based on ppolicy history settings
  - Visual indicators for password expiry warnings

- **Password policy environment variables**
  - `PASSWORD_POLICY_ENABLED` - Enable password policy features (default: FALSE)
  - `PASSWORD_MIN_LENGTH` - Minimum password length (default: 8)
  - `PASSWORD_REQUIRE_UPPERCASE` - Require uppercase letters (default: FALSE)
  - `PASSWORD_REQUIRE_LOWERCASE` - Require lowercase letters (default: FALSE)
  - `PASSWORD_REQUIRE_NUMBERS` - Require numbers (default: FALSE)
  - `PASSWORD_REQUIRE_SPECIAL` - Require special characters (default: FALSE)
  - `PASSWORD_MIN_SCORE` - Minimum password strength score (default: 3)
  - `PASSWORD_EXPIRY_DAYS` - Password expiry in days (default: 0 = never)

### Changed

  - Base container updated 
  - Module and submodule improvements
  - Added `totp_functions.inc.php` - MFA/TOTP functionality
  - Errors handling is improved - errors are sent to the logs
  - jQuery has been replaced with vanilla javascript

### Fixed

- **[#234](https://github.com/wheelybird/luminary/issues/234)** - Umlauts and Unicode characters converted to HTML entities (implemented proper UTF-8 support and LDAP escaping)
- **[#230](https://github.com/wheelybird/luminary/issues/230)** - Group creation flow issues with lastGID prefilling and empty groups (auto-generate gidNumber, allow empty groups for RFC2307)
- **[#213](https://github.com/wheelybird/luminary/issues/213), [#171](https://github.com/wheelybird/luminary/issues/171)** - PHP warnings for undefined array key 'givenname' when users have mononyms (implemented safe array access with null coalescing)
- **[#206](https://github.com/wheelybird/luminary/issues/206)** - Password changed date indicator (displays pwdChangedTime and expiry date when ppolicy overlay is enabled)
- **[#164](https://github.com/wheelybird/luminary/issues/164)** - Require current password when changing password (enforced when ppolicy is enabled for enhanced security)
- **[#225](https://github.com/wheelybird/luminary/issues/225)** - Reply-to address support for emails (EMAIL_REPLY_TO_ADDRESS environment variable)
- **[#200](https://github.com/wheelybird/luminary/issues/200)** - Account identifier field visual distinction (key icon, bold label, highlighted background)
- **[#203](https://github.com/wheelybird/luminary/issues/203)** - Checkbox input support documented (colon-separated format with special suffixes)
- **[#224](https://github.com/wheelybird/luminary/issues/224)** - Reset password email templates now used for admin and self-service password changes
- **[#219](https://github.com/wheelybird/luminary/issues/219)** - Show both email and UID fields in forms (removed conditional field hiding)
- **[#218](https://github.com/wheelybird/luminary/issues/218)** - SERVER_PATH properly applied behind reverse proxy (url() helper function)
- **[#227](https://github.com/wheelybird/luminary/issues/227)** - Tilde escape sequences in custom attribute configuration (split_escaped() function, uses ~ instead of \ to avoid shell escaping issues)
- Character encoding issues with international characters in LDAP attributes
- Form validation error messages and client/server-side validation
- Photo upload validation and error handling for invalid formats

### Backwards Compatibility

**Version 2.0.0 maintains full backwards compatibility with 1.x releases (ldap-user-manager).**

- All existing environment variables unchanged
- New MFA features disabled by default (`MFA_FEATURE_ENABLED=FALSE`)
- No breaking changes to LDAP schema requirements
- Existing user/group management unchanged
- Optional features clearly marked and disabled by default

**Upgrading from v1.11:**

1. **No configuration changes required** - Upgrade works as drop-in replacement
2. **Optional: Enable MFA** - Set `MFA_FEATURE_ENABLED=TRUE` to activate MFA features
3. **Optional: Configure MFA** - Set MFA-related environment variables as needed
4. **LDAP Schema** - If using MFA, add TOTP schema attributes (see ldap-totp-schema project)

#### LDAP Schema Requirements for MFA

Add TOTP attributes to LDAP schema.  See [ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema) for complete schema definitions and installation instructions.

### Known Issues

- **LDAP ACL verification** - Manual verification required for `totpSecret` access restrictions

### Contributors

- wheelybird - Project maintainer
- Original ldap-user-manager contributors
- Community testers and feedback

### Links

- **GitHub**: https://github.com/wheelybird/luminary
- **Docker Hub**: https://hub.docker.com/r/wheelybird/luminary
- **Issues**: https://github.com/wheelybird/luminary/issues
- **Related Projects**:
  - [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) - OpenVPN with LDAP+TOTP
  - [ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema) - LDAP schema for TOTP
  - [pam-ldap-totp-auth](https://github.com/wheelybird/pam-ldap-totp-auth) - PAM module for LDAP+TOTP

---

## [1.11] - 2024-07-20

### Added
- Selectable LDAP login attribute

See Git history for older releases.

## [1.10] - Earlier Release
## [1.9] - Earlier Release
## [1.8] - Earlier Release
## [1.7] - Earlier Release
## [1.6] - Earlier Release
## [1.5] - Earlier Release
## [1.4] - Earlier Release
## [1.3] - Earlier Release
## [1.2] - Earlier Release
## [1.1] - Earlier Release
## [1.0] - Initial Release

See Git history for details on earlier releases.
