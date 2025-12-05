<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME - System Configuration");

/**
 * Display a configuration value with default highlighting
 */
function display_config_item($key, $metadata) {
  $current_value = get_config_value($metadata['variable']);
  $default_value = $metadata['default'];
  $is_default = is_config_default($key);

  // Build searchable text for this config
  $searchable_text = $metadata['description'] . ' ' .
                     ($metadata['help'] ?? '') . ' ' .
                     ($metadata['env_var'] ?? '') . ' ' .
                     (is_array($current_value) ? implode(' ', $current_value) : $current_value);

  echo '<tr class="config-row" data-search="' . htmlspecialchars(strtolower($searchable_text)) . '" data-is-default="' . ($is_default ? '1' : '0') . '">';

  // Configuration name with help text
  echo '<th style="width: 30%;">';
  echo htmlspecialchars($metadata['description']);
  if (!empty($metadata['help'])) {
    echo '<br><small class="text-muted">' . htmlspecialchars($metadata['help']) . '</small>';
  }
  echo '</th>';

  // Current value
  echo '<td>';

  // Format value based on type
  if ($metadata['type'] == 'boolean') {
    $display_value = $current_value ? 'TRUE' : 'FALSE';
    $badge_class = $is_default ? 'bg-secondary' : 'bg-primary';
    echo '<span class="badge ' . $badge_class . '">' . $display_value . '</span>';

    if (!$is_default) {
      $default_display = $default_value ? 'TRUE' : 'FALSE';
      echo ' <small class="text-muted">(default: ' . $default_display . ')</small>';
    }
  } elseif ($metadata['type'] == 'array') {
    if (is_array($current_value) && !empty($current_value)) {
      foreach ($current_value as $item) {
        $badge_class = $is_default ? 'bg-secondary' : 'bg-primary';
        echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($item) . '</span> ';
      }
    } else {
      echo '<em class="text-muted">(empty)</em>';
    }

    if (!$is_default && is_array($default_value) && !empty($default_value)) {
      echo '<br><small class="text-muted">Default: ';
      foreach ($default_value as $item) {
        echo htmlspecialchars($item) . ', ';
      }
      echo '</small>';
    }
  } else {
    // String, integer, etc.
    $display_value = (string)$current_value;

    if ($display_value === '' || $display_value === null) {
      echo '<em class="text-muted">(not set)</em>';
    } else {
      $badge_class = $is_default ? 'bg-secondary' : 'bg-primary';

      // Use code tag for certain types
      if ($metadata['display_code'] ?? false) {
        echo '<code class="' . $badge_class . ' text-white px-2 py-1 rounded">' . htmlspecialchars($display_value) . '</code>';
      } else {
        echo '<span class="badge ' . $badge_class . '">' . htmlspecialchars($display_value) . '</span>';
      }

      if (!$is_default) {
        $default_display = (string)$default_value;
        if ($default_display !== '') {
          echo ' <small class="text-muted">(default: ' . htmlspecialchars($default_display) . ')</small>';
        }
      }
    }
  }

  // Show environment variable name if available
  if (!empty($metadata['env_var'])) {
    echo '<br><small class="text-muted">Env: <code>' . htmlspecialchars($metadata['env_var']) . '</code></small>';
  }

  echo '</td>';
  echo '</tr>';
}

?>

<div class="container-fluid">

  <h2>System configuration</h2>
  <p class="text-muted">
    Current configuration values.
    <span class="badge bg-primary">Blue badges</span> indicate values changed from defaults.
    <span class="badge bg-warning text-dark">Optional</span> features are disabled by default.
  </p>

  <!-- Search/Filter -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="configSearch" class="form-control" placeholder="Search configurations...">
        <button class="btn btn-outline-secondary" type="button" id="clearSearch">
          <i class="bi bi-x-circle"></i> Clear
        </button>
      </div>
      <small class="text-muted">Search by name, description, value, or environment variable</small>
    </div>
    <div class="col-md-6 text-end">
      <button class="btn btn-outline-primary btn-sm" id="showOnlyCustomised" type="button">
        <i class="bi bi-filter"></i> Show Only Customised
      </button>
      <button class="btn btn-outline-secondary btn-sm" id="expandAll" type="button">
        <i class="bi bi-arrows-expand"></i> Expand All
      </button>
      <button class="btn btn-outline-secondary btn-sm" id="collapseAll" type="button">
        <i class="bi bi-arrows-collapse"></i> Collapse All
      </button>
    </div>
  </div>

  <div id="configCategories">
  <?php
  // Get all categories
  $categories = get_config_categories();

  foreach ($categories as $category_key => $category) {
    // Get all configurations in this category
    $configs = get_configs_by_category($category_key);

    // Skip empty categories
    if (empty($configs)) {
      continue;
    }

    // Check if this is an optional category that's disabled
    $is_optional = $category['optional'] ?? false;
    $is_disabled = false;

    if ($is_optional) {
      // Check if the main toggle for this optional feature is enabled
      // Look for the first config in the category that ends with _ENABLED
      foreach ($configs as $key => $metadata) {
        if (strpos($key, '_ENABLED') !== false) {
          $enabled_value = get_config_value($metadata['variable']);
          if (!$enabled_value) {
            $is_disabled = true;
          }
          break;
        }
      }
    }

    // Check if category has any non-default values
    $has_changes = category_has_changes($category_key);

    // Card border and header styling
    $card_class = 'card mb-3';
    $header_class = 'card-header';

    if ($is_disabled) {
      $card_class .= ' border-secondary';
    } elseif ($is_optional) {
      $card_class .= ' border-info';
    }

    if ($has_changes) {
      $header_class .= ' border-primary border-3 border-bottom';
    }

    // Build searchable text for category
    $category_search = strtolower($category['name'] . ' ' . ($category['description'] ?? ''));

    echo '<div class="' . $card_class . ' config-category" ';
    echo 'data-category="' . htmlspecialchars($category_key) . '" ';
    echo 'data-search="' . htmlspecialchars($category_search) . '" ';
    echo 'data-has-changes="' . ($has_changes ? '1' : '0') . '" ';
    echo 'data-is-disabled="' . ($is_disabled ? '1' : '0') . '">';

    echo '<div class="' . $header_class . '" style="cursor: pointer;" onclick="toggleCategory(this)">';
    echo '<h4 class="card-title mb-0 d-flex justify-content-between align-items-center">';
    echo '<span>';

    // Category icon if available
    if (!empty($category['icon'])) {
      echo '<i class="' . htmlspecialchars($category['icon']) . '"></i> ';
    }

    echo htmlspecialchars($category['name']);

    // Badges for optional/disabled status
    if ($is_optional) {
      if ($is_disabled) {
        echo ' <span class="badge bg-secondary">Optional - Disabled</span>';
      } else {
        echo ' <span class="badge bg-success">Optional - Enabled</span>';
      }
    }

    // Badge for customised category
    if ($has_changes && !$is_disabled) {
      echo ' <span class="badge bg-primary">Customised</span>';
    }

    echo '</span>'; // Close the span with category name and badges
    echo '<i class="bi bi-chevron-down collapse-indicator"></i>';
    echo '</h4>';

    // Category description
    if (!empty($category['description'])) {
      echo '<p class="mb-0 mt-2 text-muted"><small>' . htmlspecialchars($category['description']) . '</small></p>';
    }

    echo '</div>';
    echo '<div class="card-body category-body">';

    // If optional and disabled, show how to enable
    if ($is_disabled) {
      echo '<p class="text-muted">This optional feature is currently disabled. ';

      // Find the enable environment variable
      foreach ($configs as $key => $metadata) {
        if (strpos($key, '_ENABLED') !== false && !empty($metadata['env_var'])) {
          echo 'Set <code>' . htmlspecialchars($metadata['env_var']) . '=TRUE</code> to enable.';
          break;
        }
      }

      echo '</p>';
    } else {
      // Show configuration table
      echo '<table class="table table-sm table-striped mb-0">';

      foreach ($configs as $key => $metadata) {
        display_config_item($key, $metadata);
      }

      echo '</table>';
    }

    echo '</div>';
    echo '</div>';
  }
  ?>
  </div> <!-- End configCategories -->

  <div class="alert alert-info">
    <h5><i class="bi bi-info-circle"></i> About this page</h5>
    <p class="mb-0">
      This page is automatically generated from the configuration registry.
      All configuration options are defined in <code>config.inc.php</code>.
      To add new configuration options, add them to the registry and they will automatically appear here.
    </p>
  </div>

  <!-- System Information Section -->
  <h2 class="mt-5"><i class="bi bi-info-square"></i> System information</h2>
  <p class="text-muted">Runtime environment and server details</p>

  <?php
  // Get Luminary version
  $version_file = __DIR__ . '/../VERSION';
  $luminary_version = 'Unknown';
  if (file_exists($version_file)) {
    $luminary_version = trim(file_get_contents($version_file));
  }

  // Get disk space information
  $disk_total = @disk_total_space('/');
  $disk_free = @disk_free_space('/');
  $disk_used = $disk_total ? $disk_total - $disk_free : 0;
  $disk_percent = $disk_total ? round(($disk_used / $disk_total) * 100, 1) : 0;

  // Format bytes to human readable
  function format_bytes($bytes) {
    if ($bytes >= 1099511627776) {
      return round($bytes / 1099511627776, 2) . ' TB';
    } elseif ($bytes >= 1073741824) {
      return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
      return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
      return round($bytes / 1024, 2) . ' KB';
    } else {
      return $bytes . ' bytes';
    }
  }

  // Test LDAP connection
  $ldap_status = 'Unknown';
  $ldap_status_class = 'bg-secondary';
  try {
    $test_ldap = @open_ldap_connection();
    if ($test_ldap) {
      $ldap_status = 'Connected';
      $ldap_status_class = 'bg-success';
      @ldap_close($test_ldap);
    } else {
      $ldap_status = 'Failed';
      $ldap_status_class = 'bg-danger';
    }
  } catch (Exception $e) {
    $ldap_status = 'Error: ' . $e->getMessage();
    $ldap_status_class = 'bg-danger';
  }

  // Determine TLS/SSL status from actual connection
  $tls_status = 'None (Unencrypted)';
  $tls_status_class = 'bg-warning text-dark';
  $tls_icon = 'bi-shield-x';

  if (isset($LDAP['connection_type'])) {
    if ($LDAP['connection_type'] == 'LDAPS') {
      $tls_status = 'LDAPS (SSL/TLS)';
      $tls_status_class = 'bg-success';
      $tls_icon = 'bi-shield-lock';
    } elseif ($LDAP['connection_type'] == 'StartTLS') {
      $tls_status = 'StartTLS';
      $tls_status_class = 'bg-success';
      $tls_icon = 'bi-shield-lock';
    }
  }

  ?>

  <div class="row">
    <!-- Application Information -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="bi bi-app-indicator"></i> Application</h5>
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr>
              <th style="width: 40%;">Luminary Version</th>
              <td><span class="badge bg-primary"><?php echo htmlspecialchars($luminary_version); ?></span></td>
            </tr>
            <tr>
              <th>Persistent Data Storage</th>
              <td>
                <?php
                  include_once "ldap_app_data_functions.inc.php";
                  $ldap_connection_storage = open_ldap_connection();
                  $using_ldap = false;
                  $storage_status = '/tmp (ephemeral)';
                  $storage_class = 'bg-warning';
                  $storage_icon = 'bi-folder';

                  if ($USE_LDAP_AS_DB == TRUE) {
                    if (ldap_app_data_entry_exists($ldap_connection_storage)) {
                      $using_ldap = true;
                      $storage_status = 'LDAP (persistent)';
                      $storage_class = 'bg-success';
                      $storage_icon = 'bi-database';
                    } else {
                      $storage_status = '/tmp (LDAP enabled but entry missing)';
                      $storage_class = 'bg-danger';
                      $storage_icon = 'bi-exclamation-triangle';
                    }
                  }
                  ldap_close($ldap_connection_storage);
                ?>
                <i class="bi <?php echo $storage_icon; ?>"></i>
                <span class="badge <?php echo $storage_class; ?>"><?php echo htmlspecialchars($storage_status); ?></span>
                <br><small class="text-muted">Sessions, password reset tokens</small>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <!-- Server Information -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="bi bi-server"></i> Server</h5>
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr>
              <th style="width: 40%;">Server Software</th>
              <td><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td>
            </tr>
            <tr>
              <th>Operating System</th>
              <td><?php echo php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')'; ?></td>
            </tr>
            <tr>
              <th>Hostname</th>
              <td><?php echo htmlspecialchars(gethostname()); ?></td>
            </tr>
            <tr>
              <th>Server Time</th>
              <td><?php echo date('Y-m-d H:i:s T'); ?></td>
            </tr>
            <?php if ($disk_total) { ?>
            <tr>
              <th>Disk Usage</th>
              <td>
                <?php echo format_bytes($disk_used); ?> / <?php echo format_bytes($disk_total); ?>
                <span class="badge <?php echo $disk_percent > 90 ? 'bg-danger' : ($disk_percent > 75 ? 'bg-warning' : 'bg-success'); ?>">
                  <?php echo $disk_percent; ?>%
                </span>
              </td>
            </tr>
            <?php } ?>
          </table>
        </div>
      </div>
    </div>

    <!-- LDAP Information -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="bi bi-diagram-3"></i> LDAP connection</h5>
        </div>
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tr>
              <th style="width: 40%;">Server URI</th>
              <td><code><?php echo htmlspecialchars($LDAP['uri'] ?? 'Not configured'); ?></code></td>
            </tr>
            <tr>
              <th>Base DN</th>
              <td><code><?php echo htmlspecialchars($LDAP['base_dn'] ?? 'Not configured'); ?></code></td>
            </tr>
            <tr>
              <th>User Base DN</th>
              <td><code><?php echo htmlspecialchars($LDAP['user_dn'] ?? 'Not configured'); ?></code></td>
            </tr>
            <tr>
              <th>Connection Status</th>
              <td><span class="badge <?php echo $ldap_status_class; ?>"><?php echo htmlspecialchars($ldap_status); ?></span></td>
            </tr>
            <tr>
              <th>TLS/SSL Encryption</th>
              <td><i class="<?php echo $tls_icon; ?>"></i> <span class="badge <?php echo $tls_status_class; ?>"><?php echo htmlspecialchars($tls_status); ?></span></td>
            </tr>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
// Search and filter functionality
let filterCustomisedOnly = false;

// Toggle category collapse
function toggleCategory(header) {
  const card = header.closest('.config-category');
  const body = card.querySelector('.category-body');
  const indicator = header.querySelector('.collapse-indicator');

  if (body.style.display === 'none') {
    body.style.display = 'block';
    indicator.classList.remove('bi-chevron-right');
    indicator.classList.add('bi-chevron-down');
  } else {
    body.style.display = 'none';
    indicator.classList.remove('bi-chevron-down');
    indicator.classList.add('bi-chevron-right');
  }
}

// Search configurations
function searchConfigs() {
  const searchTerm = document.getElementById('configSearch').value.toLowerCase();
  const categories = document.querySelectorAll('.config-category');

  categories.forEach(category => {
    const categorySearch = category.getAttribute('data-search');
    const hasChanges = category.getAttribute('data-has-changes') === '1';
    const isDisabled = category.getAttribute('data-is-disabled') === '1';
    const rows = category.querySelectorAll('.config-row');

    // Check if category should be shown based on customised filter
    if (filterCustomisedOnly && !hasChanges) {
      category.style.display = 'none';
      return;
    }

    let visibleRows = 0;

    // Filter rows
    rows.forEach(row => {
      const rowSearch = row.getAttribute('data-search');
      const rowIsDefault = row.getAttribute('data-is-default') === '1';

      let showRow = true;

      // Apply search filter
      if (searchTerm && !rowSearch.includes(searchTerm) && !categorySearch.includes(searchTerm)) {
        showRow = false;
      }

      // Apply customised filter
      if (filterCustomisedOnly && rowIsDefault) {
        showRow = false;
      }

      if (showRow) {
        row.style.display = '';
        visibleRows++;
      } else {
        row.style.display = 'none';
      }
    });

    // Show category if it matches search or has visible rows
    if (searchTerm && categorySearch.includes(searchTerm)) {
      category.style.display = '';
      // Expand category if it matches search
      const body = category.querySelector('.category-body');
      const indicator = category.querySelector('.collapse-indicator');
      body.style.display = 'block';
      indicator.classList.remove('bi-chevron-right');
      indicator.classList.add('bi-chevron-down');
    } else if (visibleRows > 0 || (searchTerm === '' && !filterCustomisedOnly)) {
      category.style.display = '';
    } else {
      category.style.display = 'none';
    }
  });
}

// Clear search
document.getElementById('clearSearch').addEventListener('click', function() {
  document.getElementById('configSearch').value = '';
  searchConfigs();
});

// Search on input
document.getElementById('configSearch').addEventListener('input', searchConfigs);

// Show only customised toggle
document.getElementById('showOnlyCustomised').addEventListener('click', function() {
  filterCustomisedOnly = !filterCustomisedOnly;

  if (filterCustomisedOnly) {
    this.classList.remove('btn-outline-primary');
    this.classList.add('btn-primary');
  } else {
    this.classList.remove('btn-primary');
    this.classList.add('btn-outline-primary');
  }

  searchConfigs();
});

// Expand all categories
document.getElementById('expandAll').addEventListener('click', function() {
  document.querySelectorAll('.config-category').forEach(category => {
    if (category.style.display !== 'none') {
      const body = category.querySelector('.category-body');
      const indicator = category.querySelector('.collapse-indicator');
      body.style.display = 'block';
      indicator.classList.remove('bi-chevron-right');
      indicator.classList.add('bi-chevron-down');
    }
  });
});

// Collapse all categories
document.getElementById('collapseAll').addEventListener('click', function() {
  document.querySelectorAll('.config-category').forEach(category => {
    const body = category.querySelector('.category-body');
    const indicator = category.querySelector('.collapse-indicator');
    body.style.display = 'none';
    indicator.classList.remove('bi-chevron-down');
    indicator.classList.add('bi-chevron-right');
  });
});

// Initialise - expand all categories by default
document.addEventListener('DOMContentLoaded', function() {
  // All categories are expanded by default, no action needed
  // Just ensure collapse indicators are set correctly
  document.querySelectorAll('.collapse-indicator').forEach(indicator => {
    indicator.classList.add('bi-chevron-down');
  });
});
</script>

<?php
render_footer();
?>
