# Luminary


> **Note:** This project was previously known as **ldap-user-manager**. If you're looking for the ldap-user-manager project, you're in the right place - it's now called Luminary!

Luminary is a PHP LDAP account manager; a web-based GUI interface which allows you to quickly populate a new LDAP directory and easily manage user accounts and groups.  It also has a self-service password change module.   
It's designed to work with OpenLDAP and to be run as a container.  It complements OpenLDAP containers such as [*osixia/openldap*](https://hub.docker.com/r/osixia/openldap/).

A web-based interface for managing LDAP user accounts and groups, with self-service password management and multi-factor authentication support.

## Overview

Luminary is a web application designed to simplify LDAP directory management through an intuitive web interface. It's built to work seamlessly with OpenLDAP and runs as a Docker container, making it easy to deploy alongside LDAP servers like [osixia/openldap](https://hub.docker.com/r/osixia/openldap/).

This project complements [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) by providing a user-friendly way for administrators to manage accounts and for users to self-enrol in multi-factor authentication.

## Features

- **Setup wizard**: Automatically creates the necessary LDAP structure and initial admin user
- **User management**: Create, edit, and delete user accounts with ease
- **Group management**: Organise users into groups with flexible membership controls
- **Self-service password change**: Users can securely update their own passwords
- **Multi-factor authentication**: Self-service TOTP enrolment with QR code generation
- **Email notifications**: Optional email delivery for new account credentials
- **Password tools**: Secure password generator and strength indicator
- **Account requests**: Allow users to request accounts via a web form
- **Customisable**: Brand the interface with your organisation's logo and styling

## Quick start

This example shows how to run Luminary for testing, using osixia/openldap:

### 1. Start OpenLDAP

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
  -e LDAP_RFC2307BIS_SCHEMA="true" \
  osixia/openldap:latest
```
> **Note:** if you want to enable MFA support then follow the [quick set-up guide for ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema?tab=readme-ov-file#quick-setup) which provides instructions for running the **osixia/openldap** container with support for the TOTP schema (which stores MFA information in LDAP).

### 2. Start Luminary

```bash
docker run \
  --detach \
  --name luminary \
  -p 8080:80 \
  -p 8443:443 \
  -e SERVER_HOSTNAME="localhost" \
  -e LDAP_URI="ldap://openldap:389" \
  -e LDAP_BASE_DN="dc=example,dc=com" \
  -e LDAP_REQUIRE_STARTTLS="true" \
  -e LDAP_ADMIN_BIND_DN="cn=admin,dc=example,dc=com" \
  -e LDAP_ADMIN_BIND_PWD="admin_password" \
  -e LDAP_IGNORE_CERT_ERRORS="true" \
  --link openldap:openldap \
  wheelybird/luminary:v2.0.0
```

### 3. Run the setup wizard

Visit https://localhost:8443/setup (accept the self-signed certificate warning) and follow the wizard to:
- Verify LDAP connectivity
- Create the organisational units for users and groups
- Create the admins group
- Create your first admin user

### 4. Log in

Once setup is complete, you can log in at https://localhost:8443 with your admin credentials.

## Configuration

Luminary is configured entirely through environment variables. The main categories are:

| Documentation | Description |
|---------------|-------------|
| [Configuration reference](docs/configuration.md) | Complete list of all environment variables with descriptions and defaults |
| [Multi-factor authentication](docs/mfa.md) | Setting up and using MFA/TOTP authentication |
| [Advanced topics](docs/advanced.md) | HTTPS certificates, custom attributes, email setup, and more |

### Essential configuration

The following environment variables are required:

- `LDAP_URI` - LDAP server URI (e.g., `ldap://ldap.example.com`)
- `LDAP_BASE_DN` - Base DN for your organisation (e.g., `dc=example,dc=com`)
- `LDAP_ADMIN_BIND_DN` - DN of the admin user (e.g., `cn=admin,dc=example,dc=com`)
- `LDAP_ADMIN_BIND_PWD` - Password for the admin user

All other settings have sensible defaults. For example, the admin group defaults to `admins` and can be changed with `LDAP_ADMINS_GROUP`.

### Using secrets

For sensitive values like passwords, you can use Docker secrets or mounted files. Append `_FILE` to any variable name and point it to a file containing the value:

```bash
-e LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_password
```

## Multi-factor authentication

Luminary includes comprehensive MFA support with LDAP-backed TOTP (Time-based One-Time Passwords):

- Users can self-enrol using QR codes with their authenticator apps
- Administrators can enforce MFA for specific groups
- Grace periods allow time for users to set up MFA
- Backup codes for account recovery
- Integrates seamlessly with [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp)

For detailed setup instructions, see the [MFA documentation](docs/mfa.md).

## Integration with OpenVPN

This project is designed to work alongside [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp):

1. Use Luminary to create and manage user accounts
2. Users enrol in MFA through the web interface
3. TOTP secrets are stored in LDAP
4. OpenVPN server reads the same LDAP directory for authentication
5. Users connect to VPN with password+TOTP code

Together, these projects provide a complete, centralised authentication solution.

## Development and contributions

This is an open-source project. Contributions, bug reports, and feature requests are welcome via GitHub issues and pull requests.

### Building from source

```bash
git clone https://github.com/wheelybird/luminary.git
cd luminary
docker build -t luminary .
```

## Support

- **Documentation**: See the [docs/](docs/) directory for detailed guides
- **Issues**: Report bugs or request features on [GitHub Issues](https://github.com/wheelybird/luminary/issues)
- **Discussions**: Ask questions in [GitHub Discussions](https://github.com/wheelybird/luminary/discussions)

## Licence

This project is licensed under the MIT Licence. See the LICENCE file for details.

## Related projects

- [openvpn-server-ldap-otp](https://github.com/wheelybird/openvpn-server-ldap-otp) - OpenVPN server with LDAP authentication and MFA support
- [ldap-totp-schema](https://github.com/wheelybird/ldap-totp-schema) - LDAP schema for storing TOTP configuration
- [osixia/openldap](https://hub.docker.com/r/osixia/openldap/) - Popular OpenLDAP Docker image
