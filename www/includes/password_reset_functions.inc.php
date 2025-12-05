<?php

/**
 * Password Reset Functions
 *
 * Core functions for self-service password reset feature.
 *
 * Features:
 * - Secure token generation and validation
 * - Rate limiting (3 requests per hour)
 * - Account lockout (5 failed attempts)
 * - Email verification
 * - Optional LDAP storage for horizontal scaling
 *
 * Storage:
 * - LDAP (if USE_LDAP_AS_DB enabled): cn=luminary,ou=applications description field
 * - /tmp fallback: Always available if LDAP not configured
 * - Performance cache: /tmp for 5-minute fast lookups
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

require_once "ldap_app_data_functions.inc.php";
require_once "rate_limit_functions.inc.php";

/**
 * Generate a password reset token
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @param string $email Email address (for cache filename)
 * @return string|false Token string (64 hex chars), or FALSE on failure
 */
function password_reset_generate_token($ldap_connection, $username, $email) {
  global $log_prefix, $PASSWORD_RESET_TOKEN_EXPIRY_MINUTES, $USE_LDAP_AS_DB, $LDAP_DEBUG;

  // Generate cryptographically secure token
  $token = bin2hex(random_bytes(32)); // 64 hex characters

  // Hash the token for storage (SHA-256)
  $token_hash = hash('sha256', $token);

  // Calculate expiry timestamp
  $expiry_minutes = isset($PASSWORD_RESET_TOKEN_EXPIRY_MINUTES) ? $PASSWORD_RESET_TOKEN_EXPIRY_MINUTES : 60;
  $expiry_timestamp = time() + ($expiry_minutes * 60);

  // Base64 encode the hash for LDAP storage (avoids special characters)
  $token_hash_b64 = base64_encode($token_hash);

  // Format: pwreset:USERNAME:HASH_B64:EXPIRY_TIMESTAMP
  $data_entry = "pwreset:$username:$token_hash_b64:$expiry_timestamp";

  // Try LDAP storage first if enabled
  $stored_in_ldap = FALSE;
  if ($USE_LDAP_AS_DB == TRUE && ldap_app_data_entry_exists($ldap_connection)) {
    // Remove any existing token for this user first
    ldap_app_data_remove_entry($ldap_connection, "pwreset:$username:*");

    // Store new token
    if (ldap_app_data_add_entry($ldap_connection, $data_entry)) {
      $stored_in_ldap = TRUE;
      if ($LDAP_DEBUG == TRUE) {
        error_log("$log_prefix Password reset: Token stored in LDAP for user: $username",0);
      }
    }
  }

  // Always store in /tmp for performance cache (or as fallback)
  $cache_file = "/tmp/pwreset_" . hash('sha256', $username);
  $cache_data = "$token_hash\n$expiry_timestamp\n$email";

  if (file_put_contents($cache_file, $cache_data) === FALSE) {
    error_log("$log_prefix Password reset: Failed to write cache file: $cache_file",0);
    if (!$stored_in_ldap) {
      return FALSE; // Failed both LDAP and /tmp
    }
  }

  if ($LDAP_DEBUG == TRUE) {
    $storage_type = $stored_in_ldap ? "LDAP+cache" : "/tmp cache only";
    error_log("$log_prefix Password reset: Generated token for $username (storage: $storage_type)",0);
  }

  return $token;
}

/**
 * Validate a password reset token
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @param string $token Token to validate
 * @return bool TRUE if valid, FALSE if invalid or expired
 */
function password_reset_validate_token($ldap_connection, $username, $token) {
  global $log_prefix, $USE_LDAP_AS_DB, $LDAP_DEBUG;

  // Hash the provided token
  $token_hash = hash('sha256', $token);

  $now = time();

  // Try /tmp cache first (fast path)
  $cache_file = "/tmp/pwreset_" . hash('sha256', $username);
  if (file_exists($cache_file)) {
    $cache_data = file($cache_file, FILE_IGNORE_NEW_LINES);
    if ($cache_data !== FALSE && count($cache_data) >= 2) {
      $stored_hash = $cache_data[0];
      $expiry = intval($cache_data[1]);

      // Constant-time comparison to prevent timing attacks
      if (hash_equals($stored_hash, $token_hash)) {
        if ($expiry >= $now) {
          if ($LDAP_DEBUG == TRUE) {
            error_log("$log_prefix Password reset: Valid token from cache for $username",0);
          }
          return TRUE;
        } else {
          if ($LDAP_DEBUG == TRUE) {
            error_log("$log_prefix Password reset: Expired token from cache for $username",0);
          }
          return FALSE;
        }
      }
    }
  }

  // Fall back to LDAP if enabled
  if ($USE_LDAP_AS_DB == TRUE) {
    $entries = ldap_app_data_get_entries($ldap_connection, "pwreset:$username:");

    if ($entries !== FALSE && count($entries) > 0) {
      foreach ($entries as $entry) {
        // Format: pwreset:USERNAME:HASH_B64:EXPIRY_TIMESTAMP
        $parts = explode(':', $entry);
        if (count($parts) >= 4) {
          $stored_hash_b64 = $parts[2];
          $expiry = intval($parts[3]);

          // Decode base64 hash
          $stored_hash = base64_decode($stored_hash_b64);

          // Constant-time comparison
          if (hash_equals($stored_hash, $token_hash)) {
            if ($expiry >= $now) {
              if ($LDAP_DEBUG == TRUE) {
                error_log("$log_prefix Password reset: Valid token from LDAP for $username",0);
              }
              return TRUE;
            } else {
              if ($LDAP_DEBUG == TRUE) {
                error_log("$log_prefix Password reset: Expired token from LDAP for $username",0);
              }
              return FALSE;
            }
          }
        }
      }
    }
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Password reset: Invalid token for $username",0);
  }

  return FALSE;
}

/**
 * Clean up a password reset token (after successful reset or expiry)
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @return bool TRUE on success
 */
function password_reset_cleanup_token($ldap_connection, $username) {
  global $log_prefix, $USE_LDAP_AS_DB, $LDAP_DEBUG;

  // Remove from LDAP if enabled
  if ($USE_LDAP_AS_DB == TRUE) {
    ldap_app_data_remove_entry($ldap_connection, "pwreset:$username:*");
  }

  // Remove from /tmp cache
  $cache_file = "/tmp/pwreset_" . hash('sha256', $username);
  if (file_exists($cache_file)) {
    @unlink($cache_file);
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Password reset: Cleaned up token for $username",0);
  }

  return TRUE;
}

/**
 * Check if username is rate limited
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $identifier Username
 * @return bool TRUE if allowed, FALSE if rate limited
 */
function password_reset_check_rate_limit($ldap_connection, $identifier) {
  global $log_prefix, $PASSWORD_RESET_RATE_LIMIT_REQUESTS, $PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES;
  global $USE_LDAP_AS_DB, $LDAP_DEBUG;

  $max_requests = isset($PASSWORD_RESET_RATE_LIMIT_REQUESTS) ? $PASSWORD_RESET_RATE_LIMIT_REQUESTS : 3;
  $window_minutes = isset($PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES) ? $PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES : 60;

  $identifier_hash = hash('sha256', strtolower($identifier));
  $key = "password_reset_$identifier";

  // Use generic rate limit function (always /tmp based)
  return rate_limit_check($key, $max_requests, $window_minutes);
}

/**
 * Increment rate limit counter for a username
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $identifier Username
 * @return bool TRUE on success
 */
function password_reset_increment_rate_limit($ldap_connection, $identifier) {
  global $PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES;

  $window_minutes = isset($PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES) ? $PASSWORD_RESET_RATE_LIMIT_WINDOW_MINUTES : 60;
  $key = "password_reset_$identifier";

  return rate_limit_increment($key, $window_minutes);
}

/**
 * Check if user is locked out
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @return bool TRUE if locked out, FALSE if not
 */
function password_reset_check_lockout($ldap_connection, $username) {
  global $log_prefix, $USE_LDAP_AS_DB, $LDAP_DEBUG;

  $now = time();

  // Try LDAP first if enabled
  if ($USE_LDAP_AS_DB == TRUE) {
    $entries = ldap_app_data_get_entries($ldap_connection, "lockout:$username:");

    if ($entries !== FALSE && count($entries) > 0) {
      foreach ($entries as $entry) {
        // Format: lockout:USERNAME:EXPIRY_TIMESTAMP:FAILURE_COUNT
        $parts = explode(':', $entry);
        if (count($parts) >= 3) {
          $expiry = intval($parts[2]);

          if ($expiry >= $now) {
            if ($LDAP_DEBUG == TRUE) {
              error_log("$log_prefix Password reset: User $username is locked out until " . date('Y-m-d H:i:s', $expiry),0);
            }
            return TRUE;
          }
        }
      }
    }
  }

  // Check /tmp fallback
  $lockout_file = "/tmp/pwreset_lockout_" . hash('sha256', $username);
  if (file_exists($lockout_file)) {
    $lockout_data = file($lockout_file, FILE_IGNORE_NEW_LINES);
    if ($lockout_data !== FALSE && count($lockout_data) >= 1) {
      $expiry = intval($lockout_data[0]);

      if ($expiry >= $now) {
        if ($LDAP_DEBUG == TRUE) {
          error_log("$log_prefix Password reset: User $username is locked out (from /tmp) until " . date('Y-m-d H:i:s', $expiry),0);
        }
        return TRUE;
      }
    }
  }

  return FALSE;
}

/**
 * Record a failed token validation attempt
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @return int Number of failures recorded (including this one)
 */
function password_reset_record_failure($ldap_connection, $username) {
  global $log_prefix, $PASSWORD_RESET_MAX_ATTEMPTS, $PASSWORD_RESET_LOCKOUT_DURATION_MINUTES;
  global $USE_LDAP_AS_DB, $LDAP_DEBUG;

  $max_attempts = isset($PASSWORD_RESET_MAX_ATTEMPTS) ? $PASSWORD_RESET_MAX_ATTEMPTS : 5;
  $lockout_minutes = isset($PASSWORD_RESET_LOCKOUT_DURATION_MINUTES) ? $PASSWORD_RESET_LOCKOUT_DURATION_MINUTES : 60;

  $now = time();
  $lockout_expiry = $now + ($lockout_minutes * 60);

  // Get current failure count
  $failure_count = 1;

  // Try LDAP first if enabled
  $stored_in_ldap = FALSE;
  if ($USE_LDAP_AS_DB == TRUE) {
    $entries = ldap_app_data_get_entries($ldap_connection, "lockout:$username:");

    if ($entries !== FALSE && count($entries) > 0) {
      foreach ($entries as $entry) {
        // Format: lockout:USERNAME:EXPIRY_TIMESTAMP:FAILURE_COUNT
        $parts = explode(':', $entry);
        if (count($parts) >= 4) {
          $failure_count = intval($parts[3]) + 1;

          // Remove old entry
          ldap_app_data_remove_entry($ldap_connection, "lockout:$username:*");
        }
      }
    }

    // Add new lockout entry
    $lockout_entry = "lockout:$username:$lockout_expiry:$failure_count";
    if (ldap_app_data_add_entry($ldap_connection, $lockout_entry)) {
      $stored_in_ldap = TRUE;
    }
  }

  // Store in /tmp (cache or fallback)
  $lockout_file = "/tmp/pwreset_lockout_" . hash('sha256', $username);

  if (file_exists($lockout_file)) {
    $lockout_data = file($lockout_file, FILE_IGNORE_NEW_LINES);
    if ($lockout_data !== FALSE && count($lockout_data) >= 2) {
      $failure_count = intval($lockout_data[1]) + 1;
    }
  }

  $lockout_data_str = "$lockout_expiry\n$failure_count";
  file_put_contents($lockout_file, $lockout_data_str);

  if ($LDAP_DEBUG == TRUE) {
    $storage_type = $stored_in_ldap ? "LDAP+cache" : "/tmp cache only";
    error_log("$log_prefix Password reset: Recorded failure #$failure_count for $username (storage: $storage_type)",0);
  }

  return $failure_count;
}

/**
 * Clear lockout for a user
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username
 * @return bool TRUE on success
 */
function password_reset_clear_lockout($ldap_connection, $username) {
  global $log_prefix, $USE_LDAP_AS_DB, $LDAP_DEBUG;

  // Remove from LDAP if enabled
  if ($USE_LDAP_AS_DB == TRUE) {
    ldap_app_data_remove_entry($ldap_connection, "lockout:$username:*");
  }

  // Remove from /tmp
  $lockout_file = "/tmp/pwreset_lockout_" . hash('sha256', $username);
  if (file_exists($lockout_file)) {
    @unlink($lockout_file);
  }

  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Password reset: Cleared lockout for $username",0);
  }

  return TRUE;
}

?>
