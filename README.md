# LDAP User Manager

A web-based interface for managing LDAP user accounts and groups, with self-service password management and multi-factor authentication support.

## Overview

LDAP User Manager is a web application designed to simplify LDAP directory management through an intuitive web interface. It's built to work seamlessly with OpenLDAP and runs as a Docker container, making it easy to deploy alongside LDAP servers like [osixia/openldap](https://hub.docker.com/r/osixia/openldap/).

This project complements [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) by providing a user-friendly way for administrators to manage accounts and for users to self-enrol in multi-factor authentication.

## Features

- **Setup Wizard**: Automatically creates the necessary LDAP structure and initial admin user
- **User Management**: Create, edit, and delete user accounts with ease
- **Group Management**: Organise users into groups with flexible membership controls
- **Self-Service Password Change**: Users can securely update their own passwords
- **Multi-Factor Authentication**: Self-service TOTP enrolment with QR code generation
- **Email Notifications**: Optional email delivery for new account credentials
- **Password Tools**: Secure password generator and strength indicator
- **Account Requests**: Allow users to request accounts via a web form
- **Customisable**: Brand the interface with your organisation's logo and styling

## Quick Start

This example shows how to run LDAP User Manager for testing, using osixia/openldap:

### 1. Start OpenLDAP
s
```bash
docker run \
  --detach \
  --name openldap \
  --hostname openldap \
  -p 389:389 \
  -e LDAP_ORGANISATION="Example Ltd" \
  -e LDAP_DOMAIN="example.com" \
  -e LDAP_ADMIN_PASSWORD="admin_password" \
  -e LDAP_TLS_VERIFY_CLIENT="never" \
  osixia/openldap:latest
```

### 2. Start LDAP User Manager

```bash
docker run \
  --detach \
  --name ldap-user-manager \
  -p 8080:80 \
  -p 8443:443 \
  -e SERVER_HOSTNAME="localhost" \
  -e LDAP_URI="ldap://openldap:389" \
  -e LDAP_BASE_DN="dc=example,dc=com" \
  -e LDAP_REQUIRE_STARTTLS="TRUE" \
  -e LDAP_ADMINS_GROUP="admins" \
  -e LDAP_ADMIN_BIND_DN="cn=admin,dc=example,dc=com" \
  -e LDAP_ADMIN_BIND_PWD="admin_password" \
  -e LDAP_IGNORE_CERT_ERRORS="true" \
  --link openldap:openldap \
  wheelybird/ldap-user-manager:latest
```

### 3. Run the Setup Wizard

Visit https://localhost:8443/setup (accept the self-signed certificate warning) and follow the wizard to:
- Verify LDAP connectivity
- Create the organisational units for users and groups
- Create the admins group
- Create your first admin user

### 4. Log In

Once setup is complete, you can log in at https://localhost:8443 with your admin credentials.

## Configuration

LDAP User Manager is configured entirely through environment variables. The main categories are:

| Documentation | Description |
|---------------|-------------|
| [Configuration Reference](docs/configuration.md) | Complete list of all environment variables with descriptions and defaults |
| [Multi-Factor Authentication](docs/mfa.md) | Setting up and using MFA/TOTP authentication |
| [Advanced Topics](docs/advanced.md) | HTTPS certificates, custom attributes, email setup, and more |

### Essential Configuration

The following environment variables are required:

- `LDAP_URI` - LDAP server URI (e.g., `ldap://ldap.example.com`)
- `LDAP_BASE_DN` - Base DN for your organisation (e.g., `dc=example,dc=com`)
- `LDAP_ADMIN_BIND_DN` - DN of the admin user (e.g., `cn=admin,dc=example,dc=com`)
- `LDAP_ADMIN_BIND_PWD` - Password for the admin user
- `LDAP_ADMINS_GROUP` - Group name for user manager admins (e.g., `admins`)

### Using Secrets

For sensitive values like passwords, you can use Docker secrets or mounted files. Append `_FILE` to any variable name and point it to a file containing the value:

```bash
-e LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_password
```

## Multi-Factor Authentication

LDAP User Manager includes comprehensive MFA support with LDAP-backed TOTP (Time-based One-Time Passwords):

- Users can self-enrol using QR codes with their authenticator apps
- Administrators can enforce MFA for specific groups
- Grace periods allow time for users to set up MFA
- Backup codes for account recovery
- Integrates seamlessly with [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp)

For detailed setup instructions, see the [MFA documentation](docs/mfa.md).

## Integration with OpenVPN

This project is designed to work alongside [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp):

1. Use LDAP User Manager to create and manage user accounts
2. Users enrol in MFA through the web interface
3. TOTP secrets are stored in LDAP
4. OpenVPN server reads the same LDAP directory for authentication
5. Users connect to VPN with password+TOTP code

Together, these projects provide a complete, centralised authentication solution.

## Development and Contributions

This is an open-source project. Contributions, bug reports, and feature requests are welcome via GitHub issues and pull requests.

### Building from Source

```bash
git clone https://github.com/wheelybird/ldap-user-manager.git
cd ldap-user-manager
docker build -t ldap-user-manager .
```

## Support

- **Documentation**: See the [docs/](docs/) directory for detailed guides
- **Issues**: Report bugs or request features on [GitHub Issues](https://github.com/wheelybird/ldap-user-manager/issues)
- **Discussions**: Ask questions in [GitHub Discussions](https://github.com/wheelybird/ldap-user-manager/discussions)

## Licence

This project is licensed under the MIT Licence. See the LICENCE file for details.

## Related Projects

- [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) - OpenVPN server with LDAP authentication and MFA support
- [ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema) - LDAP schema for storing TOTP configuration
- [osixia/openldap](https://hub.docker.com/r/osixia/openldap/) - Popular OpenLDAP Docker image
