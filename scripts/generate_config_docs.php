#!/usr/bin/env php
<?php
/**
 * Configuration Documentation Generator
 *
 * Generates markdown documentation from the configuration registry.
 * Run this script to update docs/configuration.md with the latest config options.
 *
 * Usage: php generate_config_docs.php
 */

// Set up paths
$includes_path = __DIR__ . '/../www/includes';
set_include_path(".:{$includes_path}");

include_once "{$includes_path}/config_registry.inc.php";

// Output file
$output_file = __DIR__ . '/../docs/configuration.md';

echo "Generating configuration documentation...\n";

// Start building markdown content
$markdown = <<<'HEADER'
# Configuration Reference

This document provides a comprehensive reference for all configuration options in Luminary.

**Note:** This documentation is auto-generated from the configuration registry.
To update this file, run: `php docs/generate_config_docs.php`

## Table of Contents

HEADER;

// Build table of contents
$categories = get_config_categories();
foreach ($categories as $category_key => $category) {
  $configs = get_configs_by_category($category_key);
  if (empty($configs)) {
    continue;
  }

  $anchor = strtolower(str_replace(' ', '-', $category['name']));
  $optional_badge = ($category['optional'] ?? false) ? ' 🔧' : '';
  $markdown .= "- [{$category['name']}{$optional_badge}](#{$anchor})\n";
}

$markdown .= "\n---\n\n";

// Legend
$markdown .= <<<'LEGEND'
## Legend

- 🔧 = Optional feature (disabled by default)
- ⚠️ = Mandatory configuration (must be set)
- 📝 = Has default value
- 🔢 = Array/list value
- ✅ = Boolean value

---

LEGEND;

// Generate documentation for each category
foreach ($categories as $category_key => $category) {
  $configs = get_configs_by_category($category_key);

  if (empty($configs)) {
    continue;
  }

  $optional_badge = ($category['optional'] ?? false) ? ' 🔧' : '';

  $markdown .= "\n## {$category['name']}{$optional_badge}\n\n";

  if (!empty($category['description'])) {
    $markdown .= "{$category['description']}\n\n";
  }

  // Optional feature notice
  if ($category['optional'] ?? false) {
    $markdown .= "> **Note:** This is an optional feature. ";

    // Find the enable variable
    foreach ($configs as $key => $metadata) {
      if (strpos($key, '_ENABLED') !== false && !empty($metadata['env_var'])) {
        $markdown .= "Set `{$metadata['env_var']}=TRUE` to enable.\n\n";
        break;
      }
    }
  }

  // Configuration table
  $markdown .= "| Configuration | Type | Default | Environment Variable | Description |\n";
  $markdown .= "|--------------|------|---------|---------------------|-------------|\n";

  foreach ($configs as $key => $metadata) {
    $description = $metadata['description'];
    $type = $metadata['type'];
    $default = $metadata['default'];
    $env_var = $metadata['env_var'] ?? '-';
    $mandatory = $metadata['mandatory'] ?? false;

    // Format type with emoji
    $type_display = $type;
    if ($type === 'boolean') {
      $type_display = '✅ boolean';
    } elseif ($type === 'array') {
      $type_display = '🔢 array';
    }

    // Format default value
    if ($default === null) {
      $default_display = $mandatory ? '⚠️ *Required*' : '*Not set*';
    } elseif ($default === true) {
      $default_display = '`TRUE`';
    } elseif ($default === false) {
      $default_display = '`FALSE`';
    } elseif (is_array($default)) {
      if (empty($default)) {
        $default_display = '`[]` (empty)';
      } else {
        $default_display = '`' . implode('`, `', array_slice($default, 0, 3));
        if (count($default) > 3) {
          $default_display .= '`, ...';
        } else {
          $default_display .= '`';
        }
      }
    } else {
      $default_display = '📝 `' . $default . '`';
    }

    // Format env var
    $env_var_display = ($env_var === '-') ? '-' : "`{$env_var}`";

    // Add help text if available
    $description_full = $description;
    if (!empty($metadata['help'])) {
      $description_full .= "<br><small>{$metadata['help']}</small>";
    }

    $markdown .= "| {$description_full} | {$type_display} | {$default_display} | {$env_var_display} | {$description} |\n";
  }

  $markdown .= "\n";

  // Detailed descriptions section for complex configurations
  $has_details = false;
  foreach ($configs as $key => $metadata) {
    if (!empty($metadata['help'])) {
      if (!$has_details) {
        $markdown .= "### Details\n\n";
        $has_details = true;
      }

      $markdown .= "#### {$metadata['description']}\n\n";
      $markdown .= "{$metadata['help']}\n\n";

      if (!empty($metadata['env_var'])) {
        $markdown .= "**Environment Variable:** `{$metadata['env_var']}`\n\n";
      }

      if ($metadata['default'] !== null) {
        $default_value = $metadata['default'];
        if (is_bool($default_value)) {
          $default_value = $default_value ? 'TRUE' : 'FALSE';
        } elseif (is_array($default_value)) {
          $default_value = implode(', ', $default_value);
        }
        $markdown .= "**Default:** `{$default_value}`\n\n";
      }

      $markdown .= "---\n\n";
    }
  }
}

// Footer
$markdown .= <<<'FOOTER'

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
*Last updated: TIMESTAMP*

FOOTER;

// Replace timestamp
$markdown = str_replace('TIMESTAMP', date('Y-m-d H:i:s T'), $markdown);

// Write to file
if (file_put_contents($output_file, $markdown)) {
  echo "✓ Documentation generated successfully!\n";
  echo "  Output: {$output_file}\n";
  echo "  Categories: " . count($categories) . "\n";

  // Count total configs
  $total_configs = 0;
  foreach ($categories as $category_key => $category) {
    $configs = get_configs_by_category($category_key);
    $total_configs += count($configs);
  }
  echo "  Configurations: {$total_configs}\n";
} else {
  echo "✗ Error: Could not write to {$output_file}\n";
  exit(1);
}

echo "\nDone!\n";
