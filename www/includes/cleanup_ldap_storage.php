#!/usr/bin/env php
<?php
/**
 * LDAP Storage Cleanup Utility
 *
 * Removes expired sessions, password reset tokens, rate limits, and lockouts from LDAP storage.
 * Can be run manually or via cron.
 */

set_include_path("./:" . __DIR__);
define('LDAP_USER_MANAGER', true);

require_once "config_registry.inc.php";
require_once "ldap_functions.inc.php";
require_once "ldap_app_data_functions.inc.php";

echo "LDAP Storage Cleanup Utility\n";
echo "============================\n\n";

$ldap_connection = open_ldap_connection();
$now = time();
$total_cleaned = 0;

// Clean expired sessions
echo "Cleaning expired sessions...\n";
$session_entries = ldap_app_data_get_entries($ldap_connection, "session:");
if ($session_entries !== FALSE) {
  foreach ($session_entries as $entry) {
    // Handle both old (4-part) and new (5-part) formats during transition
    $parts = explode(':', $entry, 5);
    $expiry = null;

    if (count($parts) == 5) {
      $expiry = $parts[4]; // New format: session:ID:USERNAME:DATA:EXPIRY
    } elseif (count($parts) == 4) {
      $expiry = $parts[3]; // Old format: session:ID:DATA:EXPIRY
    }

    if ($expiry !== null && $now >= $expiry) {
      if (ldap_app_data_remove_entry($ldap_connection, $entry)) {
        $total_cleaned++;
        echo "  Removed: $entry\n";
      }
    }
  }
}

// Clean expired password reset tokens
echo "\nCleaning expired password reset tokens...\n";
$pwreset_entries = ldap_app_data_get_entries($ldap_connection, "pwreset:");
if ($pwreset_entries !== FALSE) {
  foreach ($pwreset_entries as $entry) {
    $parts = explode(':', $entry, 4);
    if (count($parts) == 4) {
      $expiry = $parts[3];
      if ($now >= $expiry) {
        if (ldap_app_data_remove_entry($ldap_connection, $entry)) {
          $total_cleaned++;
          echo "  Removed: $entry\n";
        }
      }
    }
  }
}

// Clean old rate limit entries (older than 24 hours)
echo "\nCleaning old rate limit entries...\n";
$rate_cutoff = $now - (24 * 60 * 60);
$tmp_files = glob('/tmp/ratelimit_*');
if ($tmp_files !== false) {
  foreach ($tmp_files as $file) {
    if (filemtime($file) < $rate_cutoff) {
      @unlink($file);
      $total_cleaned++;
      echo "  Removed: " . basename($file) . "\n";
    }
  }
}

// Clean expired lockouts
echo "\nCleaning expired lockouts...\n";
$lockout_entries = ldap_app_data_get_entries($ldap_connection, "lockout:");
if ($lockout_entries !== FALSE) {
  foreach ($lockout_entries as $entry) {
    $parts = explode(':', $entry, 4);
    if (count($parts) >= 3) {
      $expiry = $parts[2];
      if ($now >= $expiry) {
        if (ldap_app_data_remove_entry($ldap_connection, $entry)) {
          $total_cleaned++;
          echo "  Removed: $entry\n";
        }
      }
    }
  }
}

ldap_close($ldap_connection);

echo "\n============================\n";
echo "Total entries cleaned: $total_cleaned\n";
echo "Cleanup complete!\n";

?>
