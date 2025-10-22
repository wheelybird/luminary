# LDAP User Manager

This is a PHP LDAP account manager; a web-based GUI interface which allows you to quickly populate a new LDAP directory and easily manage user accounts and groups.  It also has a self-service password change module.   
It's designed to work with OpenLDAP and to be run as a container.  It complements OpenLDAP containers such as [*osixia/openldap*](https://hub.docker.com/r/osixia/openldap/).

***

## Features

 * Setup wizard: this will create the necessary structure to allow you to add users and groups and will set up an initial admin user that can log into the user manager.
 * Group creation and management.
 * User account creation and management.
 * **Multi-factor authentication (MFA)**: Self-service TOTP enrollment and management with QR code generation.
 * Optionally send an email to the user with their new or updated account credentials.
 * Secure password auto-generator: click the button to generate a secure password.
 * Password strength indicator.
 * Self-service password change: non-admin users can log in to change their password.
 * An optional form for people to request accounts (request emails are sent to an administrator).

***

## Screenshots

**Edit accounts**:   

![account_overview](https://user-images.githubusercontent.com/17613683/59344255-9c692480-8d05-11e9-9207-051291bafd91.png)


**Manage group membership**:   

![group_membership](https://user-images.githubusercontent.com/17613683/59344247-97a47080-8d05-11e9-8606-0bcc40471458.png)


**Self-service password change**:   

![self_service_password_change](https://user-images.githubusercontent.com/17613683/59344258-9ffcab80-8d05-11e9-9dc2-27dfd373fcc8.png)

***

## Quick start

```
docker run \
           --detach \
           --name=lum \
           -p 80:80 \
           -p 443:443 \
           -e "SERVER_HOSTNAME=lum.example.com" \
           -e "LDAP_URI=ldap://ldap.example.com" \
           -e "LDAP_BASE_DN=dc=example,dc=com" \
           -e "LDAP_REQUIRE_STARTTLS=TRUE" \
           -e "LDAP_ADMINS_GROUP=admins" \
           -e "LDAP_ADMIN_BIND_DN=cn=admin,dc=example,dc=com" \
           -e "LDAP_ADMIN_BIND_PWD=secret"\
           -e "LDAP_IGNORE_CERT_ERRORS=true" \
           -e "EMAIL_DOMAIN=ldapusermanager.org" \
           wheelybird/ldap-user-manager:v1.11
```
Change the variable values to suit your environment.  Now go to https://lum.example.com/setup.

***

## Configuration

Configuration is via environmental variables.  Please bear the following in mind:

 * This tool needs to bind to LDAP as a user that has the permissions to modify everything under the base DN.
 * This interface is designed to work with a fresh LDAP server and should only be against existing, populated LDAP directories with caution and at your own risk.

#### Containers: using files/secrets to set configuration variables

When running the user manager as a container you can append `_FILE` to any of the configuration variables and set the value to a filepath.  Then when the container starts up it will set the appropriate configuration variable with the contents of the file.   
For example, if you're using Docker Swarm and you've set the LDAP bind password as a Docker secret (`echo "myLDAPadminPassword" | docker secret create ldap_admin_bind_pwd -`) then you can set `LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_bind_pwd`.  This will result in `LDAP_ADMIN_BIND_PWD` being set with the contents of `/run/secrets/ldap_admin_bind_pwd`.

### Mandatory:


* `LDAP_URI`:  The URI of the LDAP server, e.g. `ldap://ldap.example.com` or `ldaps://ldap.example.com`
   
* `LDAP_BASE_DN`:  The base DN for your organisation, e.g. `dc=example,dc=com`
   
* `LDAP_ADMIN_BIND_DN`: The DN for the user with permission to modify all records under `LDAP_BASE_DN`, e.g. `cn=admin,dc=example,dc=com`
   
* `LDAP_ADMIN_BIND_PWD`: The password for `LDAP_ADMIN_BIND_DN`
   
* `LDAP_ADMINS_GROUP`: The name of the group used to define accounts that can use this tool to manage LDAP accounts.  e.g. `admins`

### Optional:


#### Web server settings

* `SERVER_HOSTNAME` (default: *ldapusername.org*):  The hostname that this interface will be served from.
   
* `SERVER_PATH` (default: */*): The path to the user manager on the webserver.  Useful if running this behind a reverse proxy.
   
* `SERVER_PORT` (default: *80 or 80 & 443*): The port the webserver inside the container will listen on.  If undefined then the internal webserver will listen on ports 80 and 443 (if `NO_HTTPS` is true it's just 80) and HTTP traffic is redirected to HTTPS.  When set this will disable the redirection and the internal webserver will listen for HTTPS traffic on this port (or for HTTP traffic if `NO_HTTPS` is true).  This is for use when the container's Docker network mode is set to `host`.
   
* `NO_HTTPS` (default: *FALSE*): If you set this to *TRUE* then the server will run in HTTP mode, without any encryption.  This is insecure and should only be used for testing.  See [HTTPS certificates](#https-certificates)
   
* `SERVER_KEY_FILENAME`: (default *server.key*): The filename of the HTTPS server key file. See [HTTPS certificates](#https-certificates)
   
* `SERVER_CERT_FILENAME`: (default *server.crt*): The filename of the HTTPS certficate file. See [HTTPS certificates](#https-certificates)
   
* `CA_CERT_FILENAME`: (default *ca.crt*): The filename of the HTTPS server key file. See [HTTPS certificates](#https-certificates)
   
* `SESSION_TIMEOUT` (default: *10 minutes*):  How long before an idle session will be timed out.

#### LDAP settings

* `LDAP_USER_OU` (default: *people*):  The name of the OU used to store user accounts (without the base DN appended).
   
* `LDAP_GROUP_OU` (default: *groups*):  The name of the OU used to store groups (without the base DN appended).
   
* `LDAP_REQUIRE_STARTTLS` (default: *TRUE*):  If *TRUE* then a TLS connection is required for this interface to work.  If set to *FALSE* then the interface will work without STARTTLS, but a warning will be displayed on the page.
   
* `LDAP_IGNORE_CERT_ERRORS` (default: *FALSE*): If *TRUE* then problems with the certificate presented by the LDAP server will be ignored (for example FQDN mismatches).  Use this if your LDAP server is using a self-signed certificate and you don't have a CA certificate for it or you're connecting to a pool of different servers via round-robin DNS.
   
* `LDAP_TLS_CACERT` (no default): If you need to use a specific CA certificate for TLS connections to the LDAP server (when `LDAP_REQUIRE_STARTTLS` is set) then assign the contents of the CA certificate to this variable.  e.g. `-e LDAP_TLS_CACERT="$(</path/to/ca.crt)"` (ensure you're using quotes or you'll get an "invalid reference format: repository name must be lowercase" error).  Alternatively you can bind-mount a certificate into the container and use `LDAP_TLS_CACERT_FILE` to specify the path to the file.

#### Advanced LDAP settings

These settings should only be changed if you're trying to make the user manager work with an LDAP directory that's already populated and the defaults don't work.
   
* `LDAP_ACCOUNT_ATTRIBUTE` (default: *uid*):  The attribute used as the account identifier.  See [Account names](#account-names) for more information.
   
* `LDAP_GROUP_ATTRIBUTE` (default: *cn*):  The attribute used as the group identifier.
   
* `LDAP_GROUP_MEMBERSHIP_ATTRIBUTE` (default: *memberUID* or *uniqueMember*):  The attribute used when adding a user's account to a group.  When the `groupOfMembers` objectClass is detected `FORCE_RFC2307BIS` is `TRUE` it defaults to `uniqueMember`, otherwise it'll default to `memberUID`. Explicitly setting this variable will override any default.
   
* `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` (no default): A comma-separated list of additional objectClasses to use when creating an account.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` (no default): A comma-separated list of extra attributes to display when creating an account.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `GROUP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` (no default): A comma-separated list of additional objectClasses to use when creating a group.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.

* `GROUP_ACCOUNT_ADDITIONAL_ATTRIBUTES` (no default): A comma-separated list of extra attributes to display when creating a group.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `LDAP_GROUP_MEMBERSHIP_USES_UID` (default: *TRUE* or *FALSE*): If *TRUE* then the entry for a member of a group will be just the username, otherwise it's the member's full DN.  When the `groupOfMembers` objectClass is detected or `FORCE_RFC2307BIS` is `TRUE` it  defaults to `FALSE`, otherwise it'll default to `TRUE`. Explicitly setting this variable will override the default.
   
* `FORCE_RFC2307BIS` (default: *FALSE*): Set to *TRUE* if the auto-detection is failing to spot that the RFC2307BIS schema is available.  When *FALSE* the user manager will use auto-detection.  See [Using the RFC2307BIS schema](#using-the-rfc2307bis-schema) for more information.
   

#### User account creation settings

* `DEFAULT_USER_GROUP` (default: *everybody*):  The group that new accounts are automatically added to when created.  *NOTE*: If this group doesn't exist then a group is created with the same name as the username and the user is added to that group.
   
* `DEFAULT_USER_SHELL` (default: */bin/bash*):  The shell that will be launched when the user logs into a server.
   
* `EMAIL_DOMAIN` (no default):  If set then the email address field will be automatically populated in the form of `username@email_domain`.
   
* `ENFORCE_SAFE_SYSTEM_NAMES` (default: *TRUE*):  If set to `TRUE` (the default) this will check system login and group names against `USERNAME_REGEX` to ensure they're safe to use on servers.  See [Account names](#account-names) for more information.
   
* `USERNAME_FORMAT` (default: *{first_name}-{last_name}*):  The template used to dynamically generate the usernames stored in the `uid` attribute.  See [Username format](#username-format).
   
* `USERNAME_REGEX` (default: *^[a-z][a-zA-Z0-9\._-]{3,32}$*): The regular expression used to ensure account names and group names are safe to use on servers.  See [Username format](#username-format).
   
* `PASSWORD_HASH` (no default):  Select which hashing method which will be used to store passwords in LDAP.  Options are (in order of precedence) `SHA512CRYPT`, `SHA256CRYPT`, `MD5CRYPT`, `SSHA`, `SHA`, `SMD5`, `MD5`, `ARGON2`, `CRYPT` & `CLEAR`.  If your chosen method isn't available on your system then the strongest available method will be automatically selected - `SSHA` is the strongest method guaranteed to be available. (Note that for `ARGON2` to work your LDAP server will need to have the ARGON2 module enabled. If you don't the passwords will be saved but the user won't be able to authenticate.) Cleartext passwords should NEVER be used in any situation outside of a test.
   
* `ACCEPT_WEAK_PASSWORDS` (default: *FALSE*):  Set this to *TRUE* to prevent a password being rejected for being too weak.  The password strength indicators will still gauge the strength of the password.  Don't enable this in a production environment.


#### Website appearance and behaviour settings

* `ORGANISATION_NAME`: (default: *LDAP*): Your organisation's name.
   
* `SITE_NAME` (default: *`ORGANISATION_NAME` user manager*):  Change this to replace the title in the menu, e.g. "My Company Account Management".
   
* `SITE_LOGIN_LDAP_ATTRIBUTE` (default: *`LDAP_ACCOUNT_ATTRIBUTE`*):  The LDAP account attribute to use when logging into the user-manager.  For example, set this to `mail` to use email addresses to log in. Use this with extreme caution. The value for this attribute needs to be unique for each account; if more than one result is found when searching for an account then you won't be able to log in.
   
* `SITE_LOGIN_FIELD_LABEL` (default: *Username*):  This is the label that appears next to the username field on the login page.  If you change `SITE_LOGIN_LDAP_ATTRIBUTE` then you might want to change this.  For example, `SITE_LOGIN_FIELD_LABEL="Email address"`.
   
* `SHOW_POSIX_ATTRIBUTES` (default: *FALSE*):  If set to `TRUE` this show extra attributes for **posixAccount** and **posixGroup** in the account and group forms.  Leave this set to `FALSE` if you don't use LDAP accounts to log into servers etc., as it makes the interface much simpler.   The Posix values are still set in the background using the default values.  This setting doesn't hide any Posix attributes set via `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` or `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`.

* `REMOTE_HTTP_HEADERS_LOGIN`(default: *FALSE*) Enables session managment from an external service like Authelia. _This setting will compromise your security if you're not using an Auth-Proxy in front of this application_.


#### Email sending settings

To send emails you'll need to use an existing SMTP server.  Email sending will be disabled if `SMTP_HOSTNAME` isn't set.
   
* `SMTP_HOSTNAME` (no default): The hostname of an SMTP server - used to send emails when creating new accounts.
   
* `SMTP_HOST_PORT` (default: *25*): The SMTP port on the SMTP server.
   
* `SMTP_HELO_HOST` (no default): The hostname to send with the HELO/EHLO command.

* `SMTP_USERNAME` (no default): The username to use when the SMTP server requires authentication.
   
* `SMTP_PASSWORD` (no default): The password to use when the SMTP server requires authentication.
   
* `SMTP_USE_TLS` (default: *FALSE*): Set to TRUE if the SMTP server requires TLS to be enabled.  Overrides `SMTP_USE_SSL`.
   
* `SMTP_USE_SSL` (default: *FALSE*): Set to TRUE if the SMTP server requires SSL to be enabled.  This will be unset if `SMTP_USE_TLS` is `TRUE`.
   
* `EMAIL_FROM_ADDRESS` (default: *admin@`EMAIL_DOMAIN`*): The FROM email address used when sending out emails.  The default domain is taken from `EMAIL_DOMAIN` under **User account settings**.
   
* `EMAIL_FROM_NAME` (default: *`SITE_NAME`*): The FROM name used when sending out emails.  The default name is taken from `SITE_NAME` under **Organisation settings**.
   
* `MAIL_SUBJECT` (default: *Your `ORGANISATION_NAME` account has been created.*): The mail subject for new account emails.
   
* `NEW_ACCOUNT_EMAIL_SUBJECT`, `NEW_ACCOUNT_EMAIL_BODY`, `RESET_PASSWORD_EMAIL_SUBJECT` & `RESET_PASSWORD_EMAIL_BODY`: Change the email contents for emails sent to users when you create an account or reset a password.  See [Sending emails](#sending_emails) for full details.


**Account requests**

#### Account request settings

* `ACCOUNT_REQUESTS_ENABLED` (default: *FALSE*): Set to TRUE in order to enable a form that people can fill in to request an account.  This will send an email to `ACCOUNT_REQUESTS_EMAIL` with their details and a link to the account creation page where the details will be filled in automatically.  You'll need to set up email sending (see **Email sending**, above) for this to work.  If this is enabled but email sending isn't then requests will be disabled and an error message sent to the logs.
   
* `ACCOUNT_REQUESTS_EMAIL` (default: *{EMAIL_FROM_ADDRESS}*): This is the email address that any requests for a new account are sent to.


#### Website customization

* `$CUSTOM_LOGO` (default: *FALSE*)*: If this is defined with path to image file, then this image will be displayed in header. You need also mount volume with this file. 

* `$CUSTOM_STYLES` (default: *FALSE*)*:  If this is defined with path to css file, then this style will be used in header. Also helps vith logo positioninig. You need also mount volume with this file.

docker-compose.yml example:

```yaml
ldap-user-manager:
  environment:
    CUSTOM_LOGO: "../gfx/logo.svg"
    CUSTOM_STYLES: "../css/custom.css"
  volumes:
    - '/opt/openldap/www/gfx:/opt/ldap_user_manager/gfx'
    - '/opt/openldap/www/css:/opt/ldap_user_manager/css'
```

#### Debugging settings

* `LDAP_DEBUG` (default: *FALSE*): Set to TRUE to increase the logging level for LDAP requests.  This will output passwords to the error log - don't enable this in a production environment.  This is for information on problems updating LDAP records and such.  To debug problems connecting to the LDAP server in the first place use `LDAP_VERBOSE_CONNECTION_LOGS`.
   
* `LDAP_VERBOSE_CONNECTION_LOGS` (default: *FALSE*): Set to TRUE to enable detailed LDAP connection logs (PHP's LDAP_OPT_DEBUG_LEVEL 7).  This will flood the logs with detailled LDAP connection information so disable this for production environments.
   
* `SESSION_DEBUG` (default: *FALSE*): Set to TRUE to increase the logging level for sessions and user authorisation.  This will output cookie passkeys to the error log - don't enable this in a production environment.
   
* `SMTP_LOG_LEVEL` (default: *0*): Set to between 1-4 to get SMTP logging information (0 disables SMTP debugging logs though it will still display errors). See https://github.com/PHPMailer/PHPMailer/wiki/SMTP-Debugging for details of the levels.

***

## Initial setup

You can get the LDAP user manager running by following the [Quick start](#quick-start) instructions if you've got an LDAP server running already.  If you haven't got an LDAP server then follow the [Testing with an OpenLDAP container](#testing with-an-openldap-container) instructions.   

Once you've got got the LDAP user manager up-and-running you should run the setup wizard.   
This will create the LDAP structures that the user manager needs in order to create accounts and groups.   Go to `https://{SERVER_HOSTNAME}/setup` to get started (replace `{SERVER_HOSTNAME}` with whatever you set `SERVER_HOSTNAME` to in the Docker run command).   

The log in password is the admin user's password (the value you set for `LDAP_ADMIN_BIND_DN`).   

The setup utility will create the user and account trees, records that store the last UID and GID used when creating a user account or group, a group for admins and the initial admin account.

![initial_setup](https://user-images.githubusercontent.com/17613683/59344213-865b6400-8d05-11e9-9d86-381d59671530.png)

> The setup wizard is primarily designed to use with a new, empty LDAP directory, though it is possible to use it with existing directories as long as you ensure you use the correct advanced LDAP settings.

Once you've set up the initial administrator account you can log into the user manager with it and start creating other accounts.  Your username to log in with is (by default) whatever you set **System username** to.  See [Account names](#account-names) below if you changed the default by setting `LDAP_ACCOUNT_ATTRIBUTE`.

***

## Multi-Factor Authentication (MFA)

The LDAP User Manager includes self-service TOTP (Time-based One-Time Password) enrollment, allowing users to set up two-factor authentication with apps like Google Authenticator, Authy, or Microsoft Authenticator.

### Requirements

To use MFA, you must first install the LDAP TOTP schema in your LDAP directory:

**LDAP TOTP Schema:** https://github.com/wheelybird/ldap-totp-schema

This schema adds the necessary attributes (`totpSecret`, `totpStatus`, `totpScratchCode`, etc.) and object classes (`totpUser`, `mfaGroup`) to your LDAP directory for storing TOTP secrets and managing MFA policies.

### Features

- **Self-service enrollment**: Users can set up MFA themselves through the web interface
- **QR code generation**: Display QR codes for easy setup with authenticator apps
- **Backup codes**: Generate and display recovery codes for account recovery
- **Group-based policies**: Enforce MFA requirements at the group level
- **Grace periods**: Allow users time to set up MFA after account creation
- **Status tracking**: Monitor MFA enrollment status for all users

### Usage

Once the LDAP schema is installed:

1. **Users** can access the "Manage MFA" page to:
   - Set up TOTP with QR code scanning
   - View and save backup codes
   - Enable/disable their MFA
   - Check their enrollment status

2. **Administrators** can:
   - Create groups with MFA requirements
   - Set grace periods for MFA enrollment
   - View user MFA status in user details
   - Manage MFA policies across the organization

### Integration with Authentication Systems

TOTP secrets stored in LDAP can be used by various authentication systems:
- **OpenVPN**: Use with [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp)
- **PAM modules**: Authenticate SSH, sudo, and other PAM-enabled services
- **Custom applications**: Any system that can read from LDAP and validate TOTP codes

See the [LDAP TOTP schema documentation](https://github.com/wheelybird/ldap-totp-schema) for full implementation details and integration examples.

***

## Account names

Your login ID is whatever the *account identifier* value is for your account.  By default the user manager uses the **System username** as your login; this is actually the LDAP `uid` attribute.  So if your system username is `test-person`, that's what you'll use to log in with.    
   
The `uid` is the attribute that's normally used as the login username for systems like Linux, FreeBSD, NetBSD etc., and so is a great choice if you're using LDAP to create server accounts.   
Other services or software might use the *Common Name* (`cn`) attribute, which is normally a person's full name. So you might therefore log in as `Test Person`.   
   
The account identifier is what uniquely identifies the account, so you can't create multiple accounts where the account identifier is the same.   
You should ensure your LDAP clients use the same account identifier attribute when authenticating users.   
   
If you're using LDAP for server accounts then you'll find there are  normally constraints on how many characters and the type of characters you're allowed to use.  The user manager will validate user and group names against `USERNAME_REGEX`.  If you don't need to be so strict then you can disable these checks by setting `ENFORCE_SAFE_SYSTEM_NAMES` to `FALSE`.

***

## HTTPS certificates
The user manager runs in HTTPS mode by default and so uses HTTPS certificates.  You can pass in your own certificates by bind-mounting a local path to `/opt/ssl` in the container and then  specifying the names of the files via `SERVER_KEY_FILENAME`, `SERVER_CERT_FILENAME` and optionally `CA_CERT_FILENAME` (this will set Apache's `SSLCertificateChainFile` directive).   
If the certificate and key files don't exist then a self-signed certificate will be created when the container starts.
   
When using your own certificates, the certificate's common name (or one of the alternative names) need to match the value you set for `SERVER_HOSTNAME`.
   
For example, if your key and certificate files are in `/home/myaccount/ssl` you can bind-mount that folder by adding these lines to the `docker run` example above (place them above the final line):
```
-e "SERVER_KEY_FILENAME=lum.example.com.key" \
-e "SERVER_CERT_FILENAME=lum.example.com.crt" \
-e "CA_CERT_FILENAME=ca_bundle.pem" \
-v /home/myaccount/ssl:/opt/ssl \
```
   
If you don't want to use HTTPS certificates then set `NO_HTTPS` to **TRUE** to run in HTTP mode.  It's advised that you only do this when testing.

***

## Sending emails

When you create an account you'll have an option to send an email to the person you created the account for.  The email will give them their new username, password and a link to the self-service password change utility.   

Emails are sent via SMTP, so you'll need to be able to connect to an SMTP server and pass in the settings for that server via environmental variables - see **Email sending** above.   
If you haven't passed in those settings or if the account you've created has no (valid) email address then the option to send an email will be disabled.

When the account is created you'll be told if the email was sent or not but be aware that just because your SMTP server accepted the email it doesn't mean that it was able to deliver it.  If you get a message saying the email wasn't sent then check the logs for the error.  You can increase the log level (`SMTP_LOG_LEVEL`) to above 0 in order to see SMTP debug logs.

You can set the email subject and text for new account and password reset emails via the `NEW_ACCOUNT_EMAIL_SUBJECT`, `NEW_ACCOUNT_EMAIL_BODY`, `RESET_PASSWORD_EMAIL_SUBJECT` and `RESET_PASSWORD_EMAIL_BODY` variables.  These variables are parsed before sending and the following macros will be replaced with the relevant information:

 * `{password}` : the new password for the account
 * `{login}` : the user's login (the value of the attribute defined by `LDAP_ACCOUNT_ATTRIBUTE`.  See [Account names](#account-names) for more information.
 * `{first_name}` : the user's first name
 * `{last_name}` : the user's surname
 * `{organisation}` : the value set by `ORGANISATION_NAME`
 * `{site_url}` : a link to the user manager site using the values set by `SERVER_HOSTNAME/SERVER_PATH`
 * `{change_password_url}` : a link to the self-service password change page `SERVER_HOSTNAME/SERVER_PATH/change_password`

The email body should be in HTML.  As an example, the default email subject on creating a new account is `Your {organisation} account has been created.` and the email body is
```
You've been set up with an account for {organisation}.  Your credentials are:
<p>
Login: {login}<br>
Password: {password}
<p>
You should log into {change_password_url} and change the password as soon as possible.
```

***

## Username format

When entering a person's name the system username is automatically filled-in based on a template.  The template is defined in `USERNAME_FORMAT` and is a string containing predefined macros that are replaced with the relevant value.   
The default is `{first_name}-{last_name}` with which *Jonathan Testperson*'s username would be *jonathan-testperson*.   
Currently the available macros are:

 * `{first_name}` : the first name in lowercase
 * `{first_name_initial}` : the first letter of the first name in lowercase
 * `{last_name}`: the last name in lowercase
 * `{last_name_initial}`: the first initial of the last name in lowercase

Anything else in the `USERNAME_FORMAT` string is left unmodified.  If `ENFORCE_SAFE_SYSTEM_NAMES` is set then the username is also checked for validity against `USERNAME_REGEX`.  This is to ensure that there aren't any characters forbidden when using LDAP to create server or email accounts.   
   
If `EMAIL_DOMAIN` is set then the email address field will be automatically updated in the form of `username@email_domain`.  Entering anything manually in that field will stop the automatic update of the email field.

***

## Extra objectClasses and attributes

By default accounts are created with `person`, `inetOrgPerson` and `posixAccount` object classes.  Groups are created with `posixGroup` - if [the RFC2307BIS schema](#using-the-rfc2307bis-schema) is available then `groupOfUniqueNames` is automatically added too.   

If you need to add additional objectClasses and attributes to accounts or groups then you can add them via `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES`, `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES`, `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` and `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`.

`LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` and `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES take a comma-separated list of objectClasses to add.  For example, `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=ldappublickey,couriermailaccount`.   

`LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` and `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` take a comma-separated list of attributes to be displayed as extra fields for the account or group.    
By default these fields will be empty with the field named for the attribute, but you can set the field labels (and optionally the default values) by appending the attribute names with colon-separated values like so: `attribute_name:label:default_value`.   
Multiple attributes are separated by commas, so you can define the label and default values for several attributes as follows:  `attribute1:label1:default_value1,attribute2:label2:default_value2,attribute3:label3`.   

As an example, to set a mailbox name and quota for the `couriermailaccount` schema you can pass these variables to the container:   
```
LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=couriermailaccount
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES="mailbox:Mailbox:domain.com,quota:Mail quota:20"
```

_Note_: ObjectClasses often have attributes that _must_ have a value, so you should set a default value for these attributes, otherwise if you forget to add a value when filling in the form an error will be thrown on submission.

### Multi-value attributes

If you have an attribute that could have several values, you can add a `+` to end of the attribute name.  This will modify the form so you can add or remove extra values for that attribute.  For example, if you want to have multiple email aliases when using the _PostfixBookMailAccount_ schema then you can pass these variables to the container:   
```
LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=PostfixBookMailAccount" \
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES=mailAlias+:Email aliases"
```

### Binary attributes

If you have an attribute that stores the contents of a binary file (for example, a JPEG) then you can add a `^` to the end of the attribute name.  This will modify the form so that this attribute has an upload button.  If a JPEG has already been uploaded then it will display the image.  Otherwise the mime-type is displayed and there's a link for downloading the file.  For example, to allow you to set a user's photo:

```
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES=jpegPhoto^:Photograph"
```
The maximum filesize you can upload is 2MB.


### Caveat

These settings are advanced usage and the user manager doesn't attempt to validate any objectClasses, attributes, labels or default values you pass in.  It's up to you to ensure that your LDAP server has the appropriate schemas and that the labels and values are sane.

***

## Using the RFC2307BIS schema

Using the **RFC2307BIS** will allow you to use `memberOf` in LDAP searches which gives you an easy way to check if a user is a member of a group. For example: `(&(objectClass=posixAccount)(memberof=cn=somegroup,ou=groups,dc=ldapusermanager,dc=org))`.   
   
OpenLDAP will use the RFC2307 (NIS) schema by default; you'll need to configure your server to use the **RFC2307BIS** schema when setting up your directory.    See [this guide](https://unofficialaciguide.com/2019/07/31/ldap-schemas-for-aci-administrators-rfc2307-vs-rfc2307bis/) for more information regarding RFC2307 vs RFC2307BIS.   
Setting up RFC2307BIS is way beyond the scope of this README, but if you plan on using [osixia/openldap](https://github.com/osixia/docker-openldap) as your LDAP server then you can easily enable the RFC2307BIS schema by setting `LDAP_RFC2307BIS_SCHEMA` to `true` during the initial setup.   
   
The user manager will attempt detect if your LDAP server has the RFC2307BIS schema available and, if it does, use it when creating groups.  This will allow you to use `memberOf` in LDAP searches which gives you an easy way to check if a user is a member of a group. For example: `(&(objectClass=posixAccount)(memberof=cn=somegroup,ou=groups,dc=ldapusermanager,dc=org))`.   

If for some reason you do have the schema available but it isn't being detected then you can force the user manager to use it by setting `FORCE_RFC2307BIS` to `TRUE`.   
**Note**: if you force-enable using RFC2307BIS but your LDAP server doesn't have that schema available then creating and adding users to groups won't work and the user manager will throw errors.

***

## Testing with an OpenLDAP container

This will set up an OpenLDAP container you can use to test the user manager against.  It uses the RFC2307BIS schema.
```
docker run \
             --detach \
             --restart unless-stopped \
             --name openldap \
             -e "LDAP_ORGANISATION=ldapusermanager" \
             -e "LDAP_DOMAIN=ldapusermanager.org" \
             -e "LDAP_ADMIN_PASSWORD=change_me" \
             -e "LDAP_RFC2307BIS_SCHEMA=true" \
             -e "LDAP_REMOVE_CONFIG_AFTER_SETUP=true" \
             -e "LDAP_TLS_VERIFY_CLIENT=never" \
             -p 389:389 \
             --volume /opt/docker/openldap/var_lib_ldap:/var/lib/ldap \
             --volume /opt/docker/openldap/etc_ldap_slapd.d:/etc/ldap/slapd.d \
             osixia/openldap:latest
   
docker run \
             --detach \
             --name=lum \
             -p 80:80 \
             -p 443:443 \
             -e "SERVER_HOSTNAME=localhost" \
             -e "LDAP_URI=ldap://172.17.0.1" \
             -e "LDAP_BASE_DN=dc=ldapusermanager,dc=org" \
             -e "LDAP_ADMINS_GROUP=admins" \
             -e "LDAP_ADMIN_BIND_DN=cn=admin,dc=ldapusermanager,dc=org" \
             -e "LDAP_ADMIN_BIND_PWD=change_me" \
             -e "LDAP_IGNORE_CERT_ERRORS=true" \
             wheelybird/ldap-user-manager:latest
```
Now go to https://localhost/setup - the password is `change_me` (unless you changed it).  As this will use self-signed certificates you might need to tell your browser to ignore certificate warnings.
