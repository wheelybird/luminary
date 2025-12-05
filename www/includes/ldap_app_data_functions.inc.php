<?php

/**
 * LDAP Application Data Functions
 *
 * Helper functions for storing and retrieving application-specific persistent data
 * in a dedicated LDAP entry (cn=luminary,ou=applications).
 *
 * This provides optional persistent storage for:
 * - Password reset tokens
 * - Rate limiting data
 * - Account lockout records
 * - Session data
 * - Other persistent application state
 *
 * Benefits of LDAP storage (USE_LDAP_AS_DB=TRUE):
 * - Persistence across container restarts
 * - Horizontal scaling (multiple containers share state)
 * - Centralized management
 *
 * Falls back to /tmp storage if LDAP is not enabled or entry doesn't exist.
 * Short-lived temporary files (e.g., email attachments) should still use /tmp.
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

/**
 * Check if the LDAP application data entry exists
 *
 * @param resource $ldap_connection LDAP connection resource
 * @return bool TRUE if entry exists, FALSE otherwise
 */
function ldap_app_data_entry_exists($ldap_connection) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  $search_result = @ ldap_read($ldap_connection, $app_dn, '(objectClass=*)', array('cn'));

  if (!$search_result) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix LDAP app data entry does not exist: $app_dn",0);
    }
    return FALSE;
  }

  $entries = ldap_get_entries($ldap_connection, $search_result);

  if ($entries['count'] > 0) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix LDAP app data entry exists: $app_dn",0);
    }
    return TRUE;
  }

  return FALSE;
}

/**
 * Create the LDAP application data entry
 *
 * Creates both ou=applications and cn=luminary,ou=applications if needed
 *
 * @param resource $ldap_connection LDAP connection resource
 * @return bool TRUE on success, FALSE on failure
 */
function ldap_app_data_create_entry($ldap_connection) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  // First, check if ou=applications exists, create if not
  $ou_dn = "ou=applications,{$LDAP['base_dn']}";
  $ou_search = @ ldap_read($ldap_connection, $ou_dn, '(objectClass=*)', array('ou'));

  if (!$ou_search) {
    // Create ou=applications
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Creating ou=applications: $ou_dn",0);
    }

    $ou_entry = array(
      'objectClass' => array('organizationalUnit'),
      'ou' => 'applications',
      'description' => 'Application-specific data storage'
    );

    $ou_add = @ ldap_add($ldap_connection, $ou_dn, $ou_entry);

    if (!$ou_add) {
      ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
      error_log("$log_prefix Failed to create ou=applications: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
      return FALSE;
    }

    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Created ou=applications successfully",0);
    }
  }

  // Now create cn=luminary entry
  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Creating LDAP app data entry: $app_dn",0);
  }

  $app_entry = array(
    'objectClass' => array('device', 'extensibleObject'),
    'cn' => 'luminary',
    'description' => 'Luminary application persistent data storage'
  );

  $app_add = @ ldap_add($ldap_connection, $app_dn, $app_entry);

  if (!$app_add) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to create LDAP app data entry: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    return FALSE;
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Created LDAP app data entry successfully",0);
  }

  return TRUE;
}

/**
 * Get entries from LDAP application data storage by prefix
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $prefix Entry prefix to filter (e.g., 'pwreset:', 'ratelimit:', 'lockout:', 'session:')
 * @return array|false Array of matching entries, or FALSE on error
 */
function ldap_app_data_get_entries($ldap_connection, $prefix) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  if (!ldap_app_data_entry_exists($ldap_connection)) {
    return FALSE;
  }

  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  $search_result = @ ldap_read($ldap_connection, $app_dn, '(objectClass=*)', array('description'));

  if (!$search_result) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Failed to read LDAP app data: " . ldap_error($ldap_connection),0);
    }
    return FALSE;
  }

  $entries = ldap_get_entries($ldap_connection, $search_result);

  if ($entries['count'] == 0 || !isset($entries[0]['description'])) {
    return array();
  }

  // Filter entries by prefix
  $matching_entries = array();
  $descriptions = $entries[0]['description'];
  unset($descriptions['count']);

  foreach ($descriptions as $desc) {
    if (strpos($desc, $prefix) === 0) {
      $matching_entries[] = $desc;
    }
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Found " . count($matching_entries) . " LDAP app data entries with prefix '$prefix'",0);
  }

  return $matching_entries;
}

/**
 * Add an entry to LDAP application data storage
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $data Data string to add (format: PREFIX:KEY:DATA)
 * @return bool TRUE on success, FALSE on failure
 */
function ldap_app_data_add_entry($ldap_connection, $data) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  if (!ldap_app_data_entry_exists($ldap_connection)) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Cannot add LDAP app data: entry doesn't exist",0);
    }
    return FALSE;
  }

  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  $modify_entry = array('description' => $data);

  $modify_result = @ ldap_mod_add($ldap_connection, $app_dn, $modify_entry);

  if (!$modify_result) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to add LDAP app data entry: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    return FALSE;
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Added LDAP app data entry: $data",0);
  }

  return TRUE;
}

/**
 * Remove entries from LDAP application data storage matching a pattern
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $pattern Pattern to match (supports wildcards with *)
 * @return bool TRUE on success, FALSE on failure
 */
function ldap_app_data_remove_entry($ldap_connection, $pattern) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  if (!ldap_app_data_entry_exists($ldap_connection)) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Cannot remove LDAP app data: entry doesn't exist",0);
    }
    return FALSE;
  }

  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  // Get all description values
  $search_result = @ ldap_read($ldap_connection, $app_dn, '(objectClass=*)', array('description'));

  if (!$search_result) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix Failed to read LDAP app data for removal: " . ldap_error($ldap_connection),0);
    }
    return FALSE;
  }

  $entries = ldap_get_entries($ldap_connection, $search_result);

  if ($entries['count'] == 0 || !isset($entries[0]['description'])) {
    return TRUE; // Nothing to remove
  }

  $descriptions = $entries[0]['description'];
  unset($descriptions['count']);

  // Convert pattern to regex
  $regex_pattern = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';

  $to_remove = array();
  foreach ($descriptions as $desc) {
    if (preg_match($regex_pattern, $desc)) {
      $to_remove[] = $desc;
    }
  }

  if (count($to_remove) == 0) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix No LDAP app data entries matched pattern: $pattern",0);
    }
    return TRUE;
  }

  // Remove matched entries
  $modify_entry = array('description' => $to_remove);

  $modify_result = @ ldap_mod_del($ldap_connection, $app_dn, $modify_entry);

  if (!$modify_result) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to remove LDAP app data entries: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    return FALSE;
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Removed " . count($to_remove) . " LDAP app data entries matching pattern: $pattern",0);
  }

  return TRUE;
}

/**
 * Clean up expired entries from LDAP application data storage
 *
 * Removes:
 * - Expired password reset tokens (pwreset:*:*:TIMESTAMP where TIMESTAMP < now)
 * - Old rate limit records (ratelimit:*:TIMESTAMPS where all timestamps > 1 hour old)
 * - Expired lockout records (lockout:*:EXPIRY:* where EXPIRY < now)
 *
 * @param resource $ldap_connection LDAP connection resource
 * @return int Number of entries cleaned up, or FALSE on error
 */
function ldap_app_data_cleanup_expired($ldap_connection) {
  global $log_prefix, $LDAP, $LDAP_DEBUG;

  if (!ldap_app_data_entry_exists($ldap_connection)) {
    return FALSE;
  }

  $app_dn = "cn=luminary,ou=applications,{$LDAP['base_dn']}";

  // Get all description values
  $search_result = @ ldap_read($ldap_connection, $app_dn, '(objectClass=*)', array('description'));

  if (!$search_result) {
    return FALSE;
  }

  $entries = ldap_get_entries($ldap_connection, $search_result);

  if ($entries['count'] == 0 || !isset($entries[0]['description'])) {
    return 0;
  }

  $descriptions = $entries[0]['description'];
  unset($descriptions['count']);

  $now = time();
  $to_remove = array();

  foreach ($descriptions as $desc) {
    $parts = explode(':', $desc);
    $type = $parts[0];

    if ($type === 'pwreset' && count($parts) >= 4) {
      // pwreset:USERNAME:HASH:TIMESTAMP
      $expiry = intval($parts[3]);
      if ($expiry < $now) {
        $to_remove[] = $desc;
      }
    }
    elseif ($type === 'lockout' && count($parts) >= 3) {
      // lockout:USERNAME:EXPIRY:COUNT
      $expiry = intval($parts[2]);
      if ($expiry < $now) {
        $to_remove[] = $desc;
      }
    }
    elseif ($type === 'ratelimit' && count($parts) >= 3) {
      // ratelimit:EMAIL_HASH:TIMESTAMP1,TIMESTAMP2,...
      $timestamps_str = $parts[2];
      $timestamps = explode(',', $timestamps_str);

      // Remove if all timestamps are older than 1 hour
      $all_expired = TRUE;
      foreach ($timestamps as $ts) {
        if (intval($ts) > ($now - 3600)) {
          $all_expired = FALSE;
          break;
        }
      }

      if ($all_expired) {
        $to_remove[] = $desc;
      }
    }
  }

  if (count($to_remove) == 0) {
    if ($LDAP_DEBUG == TRUE) {
      error_log("$log_prefix No expired LDAP app data entries to clean up",0);
    }
    return 0;
  }

  // Remove expired entries
  $modify_entry = array('description' => $to_remove);

  $modify_result = @ ldap_mod_del($ldap_connection, $app_dn, $modify_entry);

  if (!$modify_result) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to clean up expired LDAP app data: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    return FALSE;
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Cleaned up " . count($to_remove) . " expired LDAP app data entries",0);
  }

  return count($to_remove);
}

?>
