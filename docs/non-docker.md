# Running Without Docker

This guide covers running LDAP User Manager directly on a server without Docker, using either Apache with mod_php or Nginx with PHP-FPM.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation Steps](#installation-steps)
- [Apache with mod_php](#apache-with-modphp)
- [Nginx with PHP-FPM](#nginx-with-php-fpm)
- [Configuration](#configuration)
- [HTTPS Setup](#https-setup)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

### System Requirements

- **Operating System**: Linux (Ubuntu, Debian, CentOS, or similar)
- **PHP**: Version 8.1 or later
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### Required PHP Extensions

```bash
# For Ubuntu/Debian
sudo apt-get install php php-ldap php-mbstring php-xml php-curl

# For CentOS/RHEL
sudo yum install php php-ldap php-mbstring php-xml php-curl
```

### Required PHP Packages

- **PHPMailer**: For sending emails (optional but recommended)

---

## Installation Steps

### 1. Download the Application

```bash
# Clone the repository
git clone https://github.com/wheelybird/ldap-user-manager.git
cd ldap-user-manager

# Or download a release
wget https://github.com/wheelybird/ldap-user-manager/archive/refs/heads/main.tar.gz
tar -xzf main.tar.gz
cd ldap-user-manager-main
```

### 2. Install PHPMailer

```bash
# Download PHPMailer
cd /opt
wget https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v7.0.0.tar.gz
tar -xzf v7.0.0.tar.gz
mv PHPMailer-7.0.0 PHPMailer
```

### 3. Set Up Application Directory

```bash
# Copy application files to web root
sudo mkdir -p /var/www/ldap-user-manager
sudo cp -r www/* /var/www/ldap-user-manager/
sudo chown -R www-data:www-data /var/www/ldap-user-manager

# Create required directories
sudo mkdir -p /var/www/ldap-user-manager/sessions
sudo chmod 700 /var/www/ldap-user-manager/sessions
```

### 4. Create Configuration File

Create `/etc/ldap-user-manager.conf`:

```bash
# LDAP Configuration
LDAP_URI=ldap://ldap.example.com
LDAP_BASE_DN=dc=example,dc=com
LDAP_ADMIN_BIND_DN=cn=admin,dc=example,dc=com
LDAP_ADMIN_BIND_PWD=your-admin-password
LDAP_ADMINS_GROUP=admins

# Optional: LDAP Settings
LDAP_USER_OU=people
LDAP_GROUP_OU=groups
LDAP_REQUIRE_STARTTLS=TRUE

# Web Server Settings
SERVER_HOSTNAME=ldap.example.com
ORGANISATION_NAME=Example Ltd

# Optional: MFA Settings
MFA_ENABLED=TRUE
MFA_REQUIRED_GROUPS=admins
MFA_GRACE_PERIOD_DAYS=7

# Optional: Email Settings
SMTP_HOSTNAME=smtp.gmail.com
SMTP_HOST_PORT=587
SMTP_USE_TLS=TRUE
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
EMAIL_DOMAIN=example.com
```

Set appropriate permissions:

```bash
sudo chmod 600 /etc/ldap-user-manager.conf
sudo chown www-data:www-data /etc/ldap-user-manager.conf
```

---

## Apache with mod_php

### Install Apache and PHP

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install apache2 libapache2-mod-php php-ldap php-mbstring php-xml php-curl

# Enable required modules
sudo a2enmod php8.1  # Adjust version as needed
sudo a2enmod ssl
sudo a2enmod rewrite
```

### Configure Virtual Host

Create `/etc/apache2/sites-available/ldap-user-manager.conf`:

```apache
<VirtualHost *:80>
    ServerName ldap.example.com
    ServerAdmin admin@example.com

    DocumentRoot /var/www/ldap-user-manager
    DirectoryIndex index.php

    <Directory /var/www/ldap-user-manager>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # PHP configuration
        php_value session.save_path "/var/www/ldap-user-manager/sessions"
        php_value session.gc_maxlifetime 600

        # Load environment variables from config file
        SetEnv CONFIG_FILE /etc/ldap-user-manager.conf
    </Directory>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/ldap-user-manager-error.log
    CustomLog ${APACHE_LOG_DIR}/ldap-user-manager-access.log combined

    # Redirect HTTP to HTTPS (when SSL is configured)
    # RewriteEngine On
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>
```

### HTTPS Virtual Host (Recommended)

Create `/etc/apache2/sites-available/ldap-user-manager-ssl.conf`:

```apache
<VirtualHost *:443>
    ServerName ldap.example.com
    ServerAdmin admin@example.com

    DocumentRoot /var/www/ldap-user-manager
    DirectoryIndex index.php

    <Directory /var/www/ldap-user-manager>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # PHP configuration
        php_value session.save_path "/var/www/ldap-user-manager/sessions"
        php_value session.gc_maxlifetime 600

        # Load environment variables from config file
        SetEnv CONFIG_FILE /etc/ldap-user-manager.conf
    </Directory>

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ldap-user-manager.crt
    SSLCertificateKeyFile /etc/ssl/private/ldap-user-manager.key
    SSLCertificateChainFile /etc/ssl/certs/ca-bundle.crt

    # Modern SSL configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/ldap-user-manager-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/ldap-user-manager-ssl-access.log combined
</VirtualHost>
```

### Enable Site and Restart Apache

```bash
# Enable the site
sudo a2ensite ldap-user-manager
sudo a2ensite ldap-user-manager-ssl

# Disable default site (optional)
sudo a2dissite 000-default

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

---

## Nginx with PHP-FPM

### Install Nginx and PHP-FPM

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install nginx php-fpm php-ldap php-mbstring php-xml php-curl

# Start PHP-FPM
sudo systemctl start php8.1-fpm  # Adjust version as needed
sudo systemctl enable php8.1-fpm
```

### Configure PHP-FPM Pool

Edit `/etc/php/8.1/fpm/pool.d/ldap-user-manager.conf`:

```ini
[ldap-user-manager]
user = www-data
group = www-data

listen = /run/php/ldap-user-manager.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; PHP configuration
php_admin_value[session.save_path] = /var/www/ldap-user-manager/sessions
php_admin_value[session.gc_maxlifetime] = 600

; Load environment variables from config file
env[CONFIG_FILE] = /etc/ldap-user-manager.conf
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.1-fpm
```

### Configure Nginx Server Block

Create `/etc/nginx/sites-available/ldap-user-manager`:

```nginx
# HTTP Server (redirects to HTTPS)
server {
    listen 80;
    listen [::]:80;
    server_name ldap.example.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ldap.example.com;

    root /var/www/ldap-user-manager;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/ldap-user-manager.crt;
    ssl_certificate_key /etc/ssl/private/ldap-user-manager.key;
    ssl_trusted_certificate /etc/ssl/certs/ca-bundle.crt;

    # Modern SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Logging
    access_log /var/log/nginx/ldap-user-manager-access.log;
    error_log /var/log/nginx/ldap-user-manager-error.log;

    # PHP handling
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/ldap-user-manager.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Pass environment variables
        fastcgi_param CONFIG_FILE /etc/ldap-user-manager.conf;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ /sessions/ {
        deny all;
    }
}
```

### Enable Site and Restart Nginx

```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/ldap-user-manager /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

---

## Configuration

### Load Environment Variables in PHP

Modify `/var/www/ldap-user-manager/includes/config.inc.php` to load from the configuration file:

Add at the beginning of the file:

```php
<?php
// Load configuration from file if running without Docker
$config_file = getenv('CONFIG_FILE');
if ($config_file && file_exists($config_file)) {
    $config = parse_ini_file($config_file);
    foreach ($config as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}
```

### Alternative: Use .htaccess (Apache only)

Create `/var/www/ldap-user-manager/.htaccess`:

```apache
# Load environment variables from config
SetEnvIf Request_URI ".*" CONFIG_FILE=/etc/ldap-user-manager.conf

# Read and set each variable
# Note: This requires manual parsing or a script
```

### Alternative: Export System-Wide

Add to `/etc/environment` or create `/etc/profile.d/ldap-user-manager.sh`:

```bash
#!/bin/bash
export LDAP_URI="ldap://ldap.example.com"
export LDAP_BASE_DN="dc=example,dc=com"
export LDAP_ADMIN_BIND_DN="cn=admin,dc=example,dc=com"
export LDAP_ADMIN_BIND_PWD="your-admin-password"
export LDAP_ADMINS_GROUP="admins"
# ... other variables
```

**Note**: This method exposes credentials in the process environment.

---

## HTTPS Setup

### Self-Signed Certificate (Testing Only)

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/ldap-user-manager.key \
    -out /etc/ssl/certs/ldap-user-manager.crt \
    -subj "/CN=ldap.example.com"

sudo chmod 600 /etc/ssl/private/ldap-user-manager.key
```

### Let's Encrypt Certificate (Production)

```bash
# Install Certbot
sudo apt-get install certbot

# For Apache
sudo apt-get install python3-certbot-apache
sudo certbot --apache -d ldap.example.com

# For Nginx
sudo apt-get install python3-certbot-nginx
sudo certbot --nginx -d ldap.example.com

# Auto-renewal
sudo certbot renew --dry-run
```

---

## Troubleshooting

### PHP Extensions Not Loaded

Check which extensions are enabled:

```bash
php -m | grep ldap
php -m | grep mbstring
```

If missing, ensure they're enabled in `php.ini`:

```bash
# Find php.ini location
php --ini

# Edit and uncomment extension lines
sudo nano /etc/php/8.1/apache2/php.ini  # For Apache
sudo nano /etc/php/8.1/fpm/php.ini      # For PHP-FPM

# Add or uncomment:
extension=ldap
extension=mbstring
extension=xml
extension=curl
```

Restart the web server after changes.

### Session Errors

Ensure the sessions directory exists and has correct permissions:

```bash
sudo mkdir -p /var/www/ldap-user-manager/sessions
sudo chown www-data:www-data /var/www/ldap-user-manager/sessions
sudo chmod 700 /var/www/ldap-user-manager/sessions
```

### LDAP Connection Errors

Test LDAP connectivity from the command line:

```bash
ldapsearch -x -H ldap://ldap.example.com -D "cn=admin,dc=example,dc=com" -w password -b "dc=example,dc=com"
```

Check PHP LDAP extension:

```bash
php -i | grep -i ldap
```

### File Permission Issues

Ensure web server user can read application files:

```bash
sudo chown -R www-data:www-data /var/www/ldap-user-manager
sudo chmod -R 755 /var/www/ldap-user-manager
sudo chmod 700 /var/www/ldap-user-manager/sessions
```

### Configuration Not Loading

Verify the config file is being read:

```bash
# Add to a test PHP file:
<?php
phpinfo();
?>
```

Check that environment variables are visible in the phpinfo() output.

### Log Files

Check application logs:

```bash
# Apache
sudo tail -f /var/log/apache2/ldap-user-manager-error.log

# Nginx
sudo tail -f /var/log/nginx/ldap-user-manager-error.log

# PHP-FPM
sudo tail -f /var/log/php8.1-fpm.log
```

---

## Additional Notes

### PHPMailer Location

Ensure PHPMailer is accessible. The application expects it at `/opt/PHPMailer`. If you've installed it elsewhere, you'll need to update the path in the PHP files:

```bash
# Find references to PHPMailer
grep -r "PHPMailer" /var/www/ldap-user-manager/includes/
```

Edit the path in `/var/www/ldap-user-manager/includes/email_functions.inc.php`.

### Performance Tuning

For production use, consider:

- Enabling OPcache
- Adjusting PHP-FPM pool settings
- Configuring appropriate session garbage collection
- Using a dedicated session storage (Redis or memcached)

### Security Considerations

- Store credentials securely (not in environment variables visible to all processes)
- Use HTTPS in production
- Restrict file permissions appropriately
- Keep PHP and web server software up to date
- Consider using PHP-FPM with a Unix socket instead of TCP
- Implement rate limiting for login attempts

---

## See Also

- [Configuration Reference](configuration.md) - All configuration options
- [Advanced Topics](advanced.md) - Reverse proxy setup and customisation
- [MFA Setup](mfa.md) - Multi-factor authentication configuration
