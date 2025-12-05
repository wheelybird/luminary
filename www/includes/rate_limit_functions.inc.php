<?php

/**
 * Rate Limiting Functions
 *
 * Generic rate limiting helpers using /tmp storage.
 * Can be used for any rate-limited operations (password reset, login attempts, API calls, etc.)
 *
 * Storage: /tmp/ratelimit_{HASH}
 * Format: One timestamp per line
 *
 * Example usage:
 *   if (rate_limit_check('password_reset_' . $email, 3, 60)) {
 *     // Allow request
 *     rate_limit_increment('password_reset_' . $email, 60);
 *   } else {
 *     // Rate limit exceeded
 *   }
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

/**
 * Check if a key is within rate limit
 *
 * @param string $key Unique identifier for this rate limit (e.g., 'pwreset_user@example.com')
 * @param int $max_requests Maximum number of requests allowed in the time window
 * @param int $window_minutes Time window in minutes
 * @return bool TRUE if request is allowed, FALSE if rate limit exceeded
 */
function rate_limit_check($key, $max_requests, $window_minutes) {
  global $log_prefix;

  $key_hash = hash('sha256', $key);
  $file_path = "/tmp/ratelimit_$key_hash";

  if (!file_exists($file_path)) {
    return TRUE; // No previous requests
  }

  $timestamps = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  if ($timestamps === FALSE) {
    error_log("$log_prefix Failed to read rate limit file: $file_path",0);
    return TRUE; // Fail open
  }

  $now = time();
  $window_start = $now - ($window_minutes * 60);

  // Count requests within the window
  $recent_requests = 0;
  foreach ($timestamps as $ts) {
    if (intval($ts) >= $window_start) {
      $recent_requests++;
    }
  }

  return ($recent_requests < $max_requests);
}

/**
 * Increment the rate limit counter for a key
 *
 * @param string $key Unique identifier for this rate limit
 * @param int $window_minutes Time window in minutes (used for cleanup)
 * @return bool TRUE on success, FALSE on failure
 */
function rate_limit_increment($key, $window_minutes) {
  global $log_prefix;

  $key_hash = hash('sha256', $key);
  $file_path = "/tmp/ratelimit_$key_hash";

  $now = time();
  $window_start = $now - ($window_minutes * 60);

  // Read existing timestamps
  $timestamps = array();
  if (file_exists($file_path)) {
    $timestamps = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($timestamps === FALSE) {
      error_log("$log_prefix Failed to read rate limit file: $file_path",0);
      $timestamps = array();
    }
  }

  // Prune old timestamps (outside the window)
  $recent_timestamps = array();
  foreach ($timestamps as $ts) {
    if (intval($ts) >= $window_start) {
      $recent_timestamps[] = $ts;
    }
  }

  // Add current timestamp
  $recent_timestamps[] = $now;

  // Write back to file
  $result = file_put_contents($file_path, implode("\n", $recent_timestamps) . "\n");

  if ($result === FALSE) {
    error_log("$log_prefix Failed to write rate limit file: $file_path",0);
    return FALSE;
  }

  return TRUE;
}

/**
 * Get remaining allowance for a key
 *
 * @param string $key Unique identifier for this rate limit
 * @param int $max_requests Maximum number of requests allowed in the time window
 * @param int $window_minutes Time window in minutes
 * @return int Number of remaining requests allowed (0 if limit exceeded)
 */
function rate_limit_get_remaining($key, $max_requests, $window_minutes) {
  $key_hash = hash('sha256', $key);
  $file_path = "/tmp/ratelimit_$key_hash";

  if (!file_exists($file_path)) {
    return $max_requests;
  }

  $timestamps = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

  if ($timestamps === FALSE) {
    return $max_requests; // Fail open
  }

  $now = time();
  $window_start = $now - ($window_minutes * 60);

  // Count requests within the window
  $recent_requests = 0;
  foreach ($timestamps as $ts) {
    if (intval($ts) >= $window_start) {
      $recent_requests++;
    }
  }

  $remaining = $max_requests - $recent_requests;
  return ($remaining > 0) ? $remaining : 0;
}

/**
 * Clear rate limit for a key
 *
 * @param string $key Unique identifier for this rate limit
 * @return bool TRUE on success, FALSE on failure
 */
function rate_limit_clear($key) {
  global $log_prefix;

  $key_hash = hash('sha256', $key);
  $file_path = "/tmp/ratelimit_$key_hash";

  if (file_exists($file_path)) {
    if (!unlink($file_path)) {
      error_log("$log_prefix Failed to clear rate limit file: $file_path",0);
      return FALSE;
    }
  }

  return TRUE;
}

?>
