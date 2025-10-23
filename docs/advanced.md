# Advanced Topics

This document covers advanced configuration scenarios, customisation options, and integration details.

## Table of Contents

- [HTTPS Certificates](#https-certificates)
- [Sending Emails](#sending-emails)
- [Username Formats](#username-formats)
- [Extra ObjectClasses and Attributes](#extra-objectclasses-and-attributes)
- [Website Customisation](#website-customisation)
- [Reverse Proxy Setup](#reverse-proxy-setup)
- [Account Name Restrictions](#account-name-restrictions)
- [Integration Examples](#integration-examples)

---

## HTTPS Certificates

By default, LDAP User Manager generates a self-signed certificate on first startup. For production use, you should provide proper SSL certificates.

### Using Your Own Certificates

Mount your certificates into the container and specify the filenames:

```bash
docker run \
  -v /path/to/certs:/opt/ssl:ro \
  -e SERVER_CERT_FILENAME=my-cert.crt \
  -e SERVER_KEY_FILENAME=my-key.key \
  -e CA_CERT_FILENAME=ca-bundle.crt \
  wheelybird/ldap-user-manager
```

Certificate files should be in PEM format.

### Certificate Requirements

- **Certificate**: Must include the full certificate chain
- **Private Key**: Must be unencrypted (no passphrase)
- **Permissions**: Container needs read access to the mounted directory

### Let's Encrypt with Certbot

When using Let's Encrypt certificates managed by Certbot:

```bash
docker run \
  -v /etc/letsencrypt/live/your-domain:/opt/ssl:ro \
  -e SERVER_CERT_FILENAME=fullchain.pem \
  -e SERVER_KEY_FILENAME=privkey.pem \
  wheelybird/ldap-user-manager
```

**Certificate Renewal**: Restart the container after certificate renewal so Apache picks up the new certificates.

### Disabling HTTPS (Not Recommended)

For testing only:

```bash
-e NO_HTTPS=TRUE
```

This serves the interface over unencrypted HTTP. **Do not use in production.**

---

## Sending Emails

LDAP User Manager can send emails when creating or updating accounts, and for account requests.

### Basic SMTP Configuration

```bash
docker run \
  -e SMTP_HOSTNAME=smtp.gmail.com \
  -e SMTP_HOST_PORT=587 \
  -e SMTP_USE_TLS=TRUE \
  -e SMTP_USERNAME=your-email@gmail.com \
  -e SMTP_PASSWORD=your-app-password \
  -e EMAIL_DOMAIN=example.com \
  -e EMAIL_FROM_ADDRESS=noreply@example.com \
  -e EMAIL_FROM_NAME="Example Ltd User Management" \
  wheelybird/ldap-user-manager
```

### Gmail Configuration

Gmail requires an "App Password" for SMTP access:

1. Enable 2-factor authentication on your Google account
2. Generate an App Password at https://myaccount.google.com/apppasswords
3. Use the App Password (not your regular Gmail password)

```bash
-e SMTP_HOSTNAME=smtp.gmail.com \
-e SMTP_HOST_PORT=587 \
-e SMTP_USE_TLS=TRUE \
-e SMTP_USERNAME=your-email@gmail.com \
-e SMTP_PASSWORD=your-app-password
```

### Office 365 / Outlook.com

```bash
-e SMTP_HOSTNAME=smtp.office365.com \
-e SMTP_HOST_PORT=587 \
-e SMTP_USE_TLS=TRUE \
-e SMTP_USERNAME=your-email@yourdomain.com \
-e SMTP_PASSWORD=your-password
```

### Amazon SES

```bash
-e SMTP_HOSTNAME=email-smtp.us-east-1.amazonaws.com \
-e SMTP_HOST_PORT=587 \
-e SMTP_USE_TLS=TRUE \
-e SMTP_USERNAME=your-ses-smtp-username \
-e SMTP_PASSWORD=your-ses-smtp-password
```

### SendGrid

```bash
-e SMTP_HOSTNAME=smtp.sendgrid.net \
-e SMTP_HOST_PORT=587 \
-e SMTP_USE_TLS=TRUE \
-e SMTP_USERNAME=apikey \
-e SMTP_PASSWORD=your-sendgrid-api-key
```

### Local Mail Server (No Authentication)

```bash
-e SMTP_HOSTNAME=localhost \
-e SMTP_HOST_PORT=25
```

Leave `SMTP_USERNAME` and `SMTP_PASSWORD` unset for unauthenticated SMTP.

### Troubleshooting Email

Enable SMTP debugging:

```bash
-e SMTP_LOG_LEVEL=3
```

Check container logs:
```bash
docker logs ldap-user-manager
```

---

## Username Formats

Control how usernames are automatically generated from user information.

### Format Templates

Use placeholders in the `USERNAME_FORMAT` variable:

```bash
# john-smith
-e USERNAME_FORMAT="{first_name}-{last_name}"

# john.smith
-e USERNAME_FORMAT="{first_name}.{last_name}"

# johnsmith
-e USERNAME_FORMAT="{first_name}{last_name}"

# jsmith
-e USERNAME_FORMAT="{first_name:1}{last_name}"

# smith (surname only)
-e USERNAME_FORMAT="{last_name}"
```

### Available Placeholders

| Placeholder | Description | Example |
|-------------|-------------|---------|
| `{first_name}` | User's first name | john |
| `{last_name}` | User's last name | smith |
| `{email_address}` | Email address | john.smith@example.com |

### Custom Validation

Control what characters are allowed in usernames:

```bash
# Default: lowercase letters, numbers, dots, hyphens, underscores, 3-32 chars
-e USERNAME_REGEX="^[a-z][a-zA-Z0-9\\._-]{3,32}$"

# Allow uppercase
-e USERNAME_REGEX="^[a-zA-Z][a-zA-Z0-9\\._-]{3,32}$"

# Email addresses as usernames
-e USERNAME_REGEX="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"
```

### Flexible Naming

To allow more flexible usernames (uppercase, spaces, etc.):

```bash
-e ENFORCE_SAFE_SYSTEM_NAMES=FALSE
```

**Warning**: Some systems may have trouble with non-POSIX usernames.

---

## Extra ObjectClasses and Attributes

Add custom objectClasses and attributes to user accounts and groups.

### Adding ObjectClasses

```bash
# Single objectClass
-e LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=mailAccount

# Multiple objectClasses (comma-separated)
-e LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=mailAccount,customUser,extraClass
```

### Adding Custom Attributes

Attributes are defined in JSON format with field types:

```bash
-e LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES='{"mailQuota":"text","homeDirectory":"text","loginShell":"shell"}'
```

### Available Field Types

| Type | Description | UI Presentation |
|------|-------------|-----------------|
| `text` | Single-line text | Text input field |
| `textarea` | Multi-line text | Textarea field |
| `password` | Password field | Masked input |
| `email` | Email address | Email input with validation |
| `number` | Numeric value | Number input |
| `shell` | Login shell | Dropdown with common shells |
| `checkbox` | Boolean value | Checkbox |

### Example: Mail Attributes

Add email-related attributes for mail servers:

```bash
-e LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=mailAccount,postfixAccount \
-e LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES='{"mailQuota":"text","mailEnabled":"checkbox","mailForwardingAddress":"email"}'
```

### Example: SSH Public Keys

```bash
-e LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=ldapPublicKey \
-e LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES='{"sshPublicKey":"textarea"}'
```

### Multi-Value Attributes

For attributes that can have multiple values, add them as separate entries in the form. The interface will submit them as an array.

### Binary Attributes

Binary attributes (like photos) are not currently supported through the web interface, but can be added via direct LDAP modification.

---

## Website Customisation

### Custom Logo

Replace the default logo with your organisation's branding:

```bash
docker run \
  -v /path/to/logo.png:/custom/logo.png:ro \
  -e CUSTOM_LOGO=/custom/logo.png \
  wheelybird/ldap-user-manager
```

**Recommended**: PNG format, transparent background, approximately 200x50 pixels.

### Custom CSS

Add custom styling to match your corporate identity:

```bash
docker run \
  -v /path/to/custom.css:/custom/styles.css:ro \
  -e CUSTOM_STYLES=/custom/styles.css \
  wheelybird/ldap-user-manager
```

**Example custom.css**:
```css
/* Change header colour */
.navbar-default {
    background-color: #2c3e50;
    border-color: #1a242f;
}

/* Custom button colours */
.btn-primary {
    background-color: #3498db;
    border-color: #2980b9;
}

/* Custom panel headings */
.panel-heading {
    background-color: #ecf0f1;
    color: #2c3e50;
}
```

### Organisation Branding

```bash
-e ORGANISATION_NAME="Example Ltd" \
-e SITE_NAME="Example Ltd Account Manager"
```

These values appear in:
- Page titles
- Login page
- Email templates
- Navigation header

---

## Reverse Proxy Setup

Running LDAP User Manager behind a reverse proxy like Nginx or Apache.

### Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name users.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://ldap-user-manager:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Container configuration**:
```bash
docker run \
  -e NO_HTTPS=TRUE \
  -e SERVER_HOSTNAME=users.example.com \
  wheelybird/ldap-user-manager
```

### Subpath Deployment

To serve at `https://example.com/ldap/`:

**Nginx**:
```nginx
location /ldap/ {
    proxy_pass http://ldap-user-manager:80/;
    proxy_set_header Host $host;
}
```

**Container**:
```bash
-e SERVER_PATH=/ldap/ \
-e NO_HTTPS=TRUE
```

### Apache Configuration

```apache
<VirtualHost *:443>
    ServerName users.example.com

    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem

    ProxyPass / http://ldap-user-manager:80/
    ProxyPassReverse / http://ldap-user-manager:80/

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
</VirtualHost>
```

---

## Account Name Restrictions

### POSIX Compliance

When `ENFORCE_SAFE_SYSTEM_NAMES=TRUE` (default), usernames and group names must be valid POSIX account names:

**Requirements**:
- Start with a lowercase letter
- Contain only: lowercase letters, numbers, dots, hyphens, underscores
- Be 3-32 characters long
- Match the `USERNAME_REGEX` pattern

**Why**: Ensures compatibility with Linux/Unix systems, SSH, mail servers, and other services that expect POSIX-compliant names.

### Relaxed Naming

To allow more flexibility:

```bash
-e ENFORCE_SAFE_SYSTEM_NAMES=FALSE
```

**Allows**:
- Uppercase letters
- Spaces
- Special characters
- Email addresses as usernames

**Warning**: Some systems may not work correctly with non-POSIX usernames.

### Group Names

Group names follow the same rules as usernames. The regex applies to both.

---

## Integration Examples

### Docker Compose with OpenLDAP

```yaml
version: '3.8'

services:
  openldap:
    image: osixia/openldap:latest
    hostname: openldap
    environment:
      LDAP_ORGANISATION: "Example Ltd"
      LDAP_DOMAIN: "example.com"
      LDAP_ADMIN_PASSWORD: "admin_password"
    volumes:
      - ldap-data:/var/lib/ldap
      - ldap-config:/etc/ldap/slapd.d
      - ./totp-schema.ldif:/container/service/slapd/assets/config/bootstrap/ldif/custom/totp-schema.ldif

  ldap-user-manager:
    image: wheelybird/ldap-user-manager:latest
    ports:
      - "8080:80"
      - "8443:443"
    environment:
      SERVER_HOSTNAME: "localhost"
      LDAP_URI: "ldap://openldap:389"
      LDAP_BASE_DN: "dc=example,dc=com"
      LDAP_REQUIRE_STARTTLS: "TRUE"
      LDAP_ADMINS_GROUP: "admins"
      LDAP_ADMIN_BIND_DN: "cn=admin,dc=example,dc=com"
      LDAP_ADMIN_BIND_PWD: "admin_password"
      LDAP_IGNORE_CERT_ERRORS: "true"
      MFA_ENABLED: "TRUE"
      MFA_REQUIRED_GROUPS: "admins"
      MFA_GRACE_PERIOD_DAYS: "7"
      EMAIL_DOMAIN: "example.com"
    depends_on:
      - openldap

volumes:
  ldap-data:
  ldap-config:
```

### Docker Swarm with Secrets

```yaml
version: '3.8'

services:
  ldap-user-manager:
    image: wheelybird/ldap-user-manager:latest
    ports:
      - "8443:443"
    environment:
      LDAP_URI: "ldaps://ldap.example.com"
      LDAP_BASE_DN: "dc=example,dc=com"
      LDAP_ADMINS_GROUP: "admins"
      LDAP_ADMIN_BIND_DN: "cn=admin,dc=example,dc=com"
      LDAP_ADMIN_BIND_PWD_FILE: /run/secrets/ldap_admin_pwd
      SMTP_HOSTNAME: smtp.example.com
      SMTP_USERNAME_FILE: /run/secrets/smtp_username
      SMTP_PASSWORD_FILE: /run/secrets/smtp_password
    secrets:
      - ldap_admin_pwd
      - smtp_username
      - smtp_password
    deploy:
      replicas: 2
      placement:
        constraints:
          - node.role == worker

secrets:
  ldap_admin_pwd:
    external: true
  smtp_username:
    external: true
  smtp_password:
    external: true
```

### Kubernetes Deployment

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: ldap-user-manager-config
data:
  LDAP_URI: "ldap://openldap.default.svc.cluster.local"
  LDAP_BASE_DN: "dc=example,dc=com"
  LDAP_ADMINS_GROUP: "admins"
  LDAP_ADMIN_BIND_DN: "cn=admin,dc=example,dc=com"
  MFA_ENABLED: "TRUE"

---
apiVersion: v1
kind: Secret
metadata:
  name: ldap-user-manager-secrets
type: Opaque
stringData:
  ldap-admin-password: "changeme"

---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: ldap-user-manager
spec:
  replicas: 2
  selector:
    matchLabels:
      app: ldap-user-manager
  template:
    metadata:
      labels:
        app: ldap-user-manager
    spec:
      containers:
      - name: ldap-user-manager
        image: wheelybird/ldap-user-manager:latest
        ports:
        - containerPort: 443
          name: https
        envFrom:
        - configMapRef:
            name: ldap-user-manager-config
        env:
        - name: LDAP_ADMIN_BIND_PWD
          valueFrom:
            secretKeyRef:
              name: ldap-user-manager-secrets
              key: ldap-admin-password

---
apiVersion: v1
kind: Service
metadata:
  name: ldap-user-manager
spec:
  selector:
    app: ldap-user-manager
  ports:
  - port: 443
    targetPort: 443
  type: LoadBalancer
```

---

## Troubleshooting

### Debug Logging

Enable verbose logging to troubleshoot issues:

```bash
-e LDAP_DEBUG=TRUE \
-e LDAP_VERBOSE_CONNECTION_LOGS=TRUE \
-e SESSION_DEBUG=TRUE \
-e SMTP_LOG_LEVEL=3
```

View logs:
```bash
docker logs -f ldap-user-manager
```

### Common Issues

**"Cannot connect to LDAP server"**
- Check `LDAP_URI` is correct
- Verify network connectivity
- Check firewall rules
- For LDAPS, verify certificate is valid

**"LDAP bind failed"**
- Check `LDAP_ADMIN_BIND_DN` and `LDAP_ADMIN_BIND_PWD`
- Verify the bind DN exists in LDAP
- Check password is correct

**"Setup wizard shows errors"**
- Ensure OUs don't already exist (or allow setup to create them)
- Check admin bind DN has permission to create OUs
- Verify base DN is correct

**"Cannot send emails"**
- Verify SMTP settings
- Check SMTP credentials
- Enable `SMTP_LOG_LEVEL=3` for debugging
- Check firewall allows outbound SMTP traffic

### Getting Help

- **Documentation**: Check the [docs/](.) directory
- **Issues**: Search or create an issue on [GitHub](https://github.com/wheelybird/ldap-user-manager/issues)
- **Discussions**: Ask questions in [GitHub Discussions](https://github.com/wheelybird/ldap-user-manager/discussions)
