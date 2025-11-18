# Documentation

This directory contains documentation for the Luminary LDAP User Manager.

## Documentation Files

### User Documentation

- **[configuration-guide.md](configuration-guide.md)** - Comprehensive configuration guide with examples and best practices
  - Getting started and Docker secrets
  - Web server configuration (SSL, ports, reverse proxy)
  - Advanced LDAP configuration
  - User profile customization with detailed examples
  - Email setup
  - Common scenarios and troubleshooting

- **[configuration.md](configuration.md)** - Complete configuration reference (auto-generated)
  - Quick reference table for all configuration options
  - Auto-generated from the configuration registry
  - Organized by category with defaults, types, and environment variables

- **[mfa.md](mfa.md)** - Multi-factor authentication guide
- **[advanced.md](advanced.md)** - Advanced topics and customization
- **[non-docker.md](non-docker.md)** - Running Luminary without Docker

### Developer Documentation

- **generate_config_docs.php** - Script to regenerate configuration.md from the config registry

## Which Documentation Should I Read?

### For Initial Setup
Start with **[configuration-guide.md](configuration-guide.md)** - It provides step-by-step instructions, real-world examples, and best practices.

### For Quick Reference
Use **[configuration.md](configuration.md)** - It's a comprehensive auto-generated table of all options with defaults and types.

### For Advanced Features
- MFA/TOTP: See **[mfa.md](mfa.md)**
- Custom schemas, objectClasses: See **[advanced.md](advanced.md)**

## Regenerating Configuration Documentation

The configuration documentation is automatically generated from the configuration registry (`www/includes/config_registry.inc.php`).

### From within Docker container:

```bash
docker exec -it <container-name> php /opt/ldap_user_manager/docs/generate_config_docs.php
```

### From host with PHP installed:

```bash
cd docs
php generate_config_docs.php
```

### During development:

After adding new configuration options to the registry, regenerate the documentation to keep it in sync:

1. Add your configuration to `www/includes/config_registry.inc.php`
2. Run the generator script (see above)
3. Review the updated `configuration.md`
4. If adding complex features, update `configuration-guide.md` with detailed examples and instructions
5. Commit both the registry changes and updated documentation

## Viewing Current Configuration

For runtime configuration values, log in as an administrator and navigate to:

**Main Menu → System Config**

This page shows:
- All current configuration values
- Highlight of values changed from defaults
- Search and filter capabilities
- Categories organized by feature area

## Configuration Registry

The configuration registry (`www/includes/config_registry.inc.php`) is the single source of truth for:

1. **System Config Page** - Auto-generates the admin configuration viewer
2. **Documentation** - Auto-generates configuration.md
3. **Future Tools** - Validation, migration scripts, config auditing

### Adding New Configurations

To add a new configuration option:

```php
'MY_CONFIG' => array(
  'category' => 'interface',              // Which category it belongs to
  'description' => 'Short description',   // User-friendly name
  'help' => 'Detailed explanation...',    // Optional detailed help text
  'type' => 'string',                     // string, boolean, integer, array
  'default' => 'value',                   // Default value (or null if mandatory)
  'mandatory' => false,                   // true if must be set
  'env_var' => 'MY_CONFIG',              // Environment variable name
  'variable' => '$MY_CONFIG',            // PHP variable path
  'display_code' => false                // true to display in monospace
),
```

After adding, both the System Config page and documentation will automatically include it.

## Optional Features

Optional features are defined in the registry with `'optional' => true` in their category definition. Examples:

- Audit Logging
- Password Policy Enforcement
- Account Lifecycle Management
- Enhanced Group Management

Each optional feature has an `*_ENABLED` configuration variable that controls whether it's active.
