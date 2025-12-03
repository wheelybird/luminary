<?php

/**
 * Audit Logging Functions
 *
 * Provides functions for logging administrative actions and security events
 * to an audit trail for compliance and security monitoring.
 */

/**
 * Write an audit log entry
 *
 * @param string $action    Action performed (e.g., 'user_created', 'mfa_enrolled', 'login_success')
 * @param string $target    Target of the action (e.g., username, group name)
 * @param string $details   Additional details about the action (optional)
 * @param string $result    Result of the action: 'success', 'failure', 'warning' (default: 'success')
 * @param string $actor     User performing the action (defaults to current user)
 * @return bool             True if log written successfully, false otherwise
 */
function audit_log($action, $target, $details = '', $result = 'success', $actor = null) {
  global $AUDIT_ENABLED, $AUDIT_LOG_FILE, $USER_ID, $log_prefix;

  // Return early if audit logging is disabled
  if (!$AUDIT_ENABLED) {
    return true;
  }

  // Use current user if no actor specified
  if ($actor === null) {
    $actor = isset($USER_ID) ? $USER_ID : 'system';
  }

  // Get client IP address
  $ip_address = get_client_ip();

  // Build log entry in structured format
  $timestamp = date('Y-m-d H:i:s T');
  $log_entry = array(
    'timestamp' => $timestamp,
    'actor' => $actor,
    'ip' => $ip_address,
    'action' => $action,
    'target' => $target,
    'result' => $result,
    'details' => $details
  );

  // Format as JSON for structured logging
  $log_line = json_encode($log_entry) . "\n";

  // Also log to syslog for redundancy
  $syslog_message = "$log_prefix AUDIT: actor=$actor ip=$ip_address action=$action target=$target result=$result";
  if (!empty($details)) {
    $syslog_message .= " details=$details";
  }
  error_log($syslog_message);

  // Handle different log destinations
  if ($AUDIT_LOG_FILE === 'stdout' || $AUDIT_LOG_FILE === 'php://stdout') {
    // Write to STDOUT (for Docker)
    $result = @file_put_contents('php://stdout', $log_line);
    if ($result === false) {
      error_log("$log_prefix Failed to write audit log to STDOUT");
      return false;
    }
  } elseif ($AUDIT_LOG_FILE === 'stderr' || $AUDIT_LOG_FILE === 'php://stderr') {
    // Write to STDERR (alternative for Docker)
    $result = @file_put_contents('php://stderr', $log_line);
    if ($result === false) {
      error_log("$log_prefix Failed to write audit log to STDERR");
      return false;
    }
  } else {
    // Write to file
    $log_dir = dirname($AUDIT_LOG_FILE);

    // Create log directory if it doesn't exist
    if (!is_dir($log_dir)) {
      if (!@mkdir($log_dir, 0750, true)) {
        error_log("$log_prefix Failed to create audit log directory: $log_dir");
        return false;
      }
    }

    // Write log entry
    $result = @file_put_contents($AUDIT_LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);

    if ($result === false) {
      error_log("$log_prefix Failed to write to audit log: $AUDIT_LOG_FILE");
      return false;
    }
  }

  return true;
}

/**
 * Check if audit logging is using STDOUT/STDERR
 *
 * @return bool True if using STDOUT/STDERR (Docker mode)
 */
function audit_is_using_stdout() {
  global $AUDIT_LOG_FILE;

  return ($AUDIT_LOG_FILE === 'stdout' || $AUDIT_LOG_FILE === 'php://stdout' ||
          $AUDIT_LOG_FILE === 'stderr' || $AUDIT_LOG_FILE === 'php://stderr');
}

/**
 * Get client IP address (handles proxies)
 *
 * @return string Client IP address
 */
function get_client_ip() {
  // Check for common proxy headers
  $ip_headers = array(
    'HTTP_CF_CONNECTING_IP',     // Cloudflare
    'HTTP_X_FORWARDED_FOR',      // Standard proxy header
    'HTTP_X_REAL_IP',            // Nginx proxy
    'HTTP_CLIENT_IP',            // Some proxies
    'REMOTE_ADDR'                // Direct connection
  );

  foreach ($ip_headers as $header) {
    if (!empty($_SERVER[$header])) {
      $ip = $_SERVER[$header];

      // X-Forwarded-For can contain multiple IPs
      if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
      }

      // Validate IP address
      if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
      }
    }
  }

  return 'unknown';
}

/**
 * Read audit log entries
 *
 * @param int    $limit     Maximum number of entries to return (default: 100)
 * @param int    $offset    Number of entries to skip (for pagination, default: 0)
 * @param string $filter    Optional filter for action, actor, or target
 * @param string $result    Optional filter by result: 'success', 'failure', 'warning', or 'all' (default: 'all')
 * @return array            Array of audit log entries (newest first)
 */
function audit_read_log($limit = 100, $offset = 0, $filter = '', $result = 'all') {
  global $AUDIT_ENABLED, $AUDIT_LOG_FILE;

  // Return empty array if audit logging is disabled
  if (!$AUDIT_ENABLED) {
    return array();
  }

  // Return empty array if using STDOUT (logs are in Docker, not readable from PHP)
  if ($AUDIT_LOG_FILE === 'stdout' || $AUDIT_LOG_FILE === 'php://stdout' ||
      $AUDIT_LOG_FILE === 'stderr' || $AUDIT_LOG_FILE === 'php://stderr') {
    return array();
  }

  // Return empty array if log file doesn't exist
  if (!file_exists($AUDIT_LOG_FILE)) {
    return array();
  }

  // Read log file
  $log_contents = @file_get_contents($AUDIT_LOG_FILE);
  if ($log_contents === false) {
    return array();
  }

  // Parse log entries (one JSON object per line)
  $lines = explode("\n", trim($log_contents));
  $entries = array();

  foreach ($lines as $line) {
    if (empty($line)) {
      continue;
    }

    $entry = @json_decode($line, true);
    if ($entry === null) {
      // Skip malformed entries
      continue;
    }

    // Apply result filter
    if ($result !== 'all' && isset($entry['result']) && $entry['result'] !== $result) {
      continue;
    }

    // Apply text filter (search in action, actor, target, details)
    if (!empty($filter)) {
      $filter_lower = strtolower($filter);
      $searchable = strtolower(
        ($entry['action'] ?? '') . ' ' .
        ($entry['actor'] ?? '') . ' ' .
        ($entry['target'] ?? '') . ' ' .
        ($entry['details'] ?? '')
      );

      if (strpos($searchable, $filter_lower) === false) {
        continue;
      }
    }

    $entries[] = $entry;
  }

  // Reverse to get newest first
  $entries = array_reverse($entries);

  // Apply pagination
  $entries = array_slice($entries, $offset, $limit);

  return $entries;
}

/**
 * Count total audit log entries
 *
 * @param string $filter  Optional filter for action, actor, or target
 * @param string $result  Optional filter by result
 * @return int            Total number of matching entries
 */
function audit_count_entries($filter = '', $result = 'all') {
  global $AUDIT_ENABLED, $AUDIT_LOG_FILE;

  if (!$AUDIT_ENABLED) {
    return 0;
  }

  // Return 0 if using STDOUT (logs are in Docker, not readable from PHP)
  if ($AUDIT_LOG_FILE === 'stdout' || $AUDIT_LOG_FILE === 'php://stdout' ||
      $AUDIT_LOG_FILE === 'stderr' || $AUDIT_LOG_FILE === 'php://stderr') {
    return 0;
  }

  if (!file_exists($AUDIT_LOG_FILE)) {
    return 0;
  }

  $log_contents = @file_get_contents($AUDIT_LOG_FILE);
  if ($log_contents === false) {
    return 0;
  }

  $lines = explode("\n", trim($log_contents));
  $count = 0;

  foreach ($lines as $line) {
    if (empty($line)) {
      continue;
    }

    $entry = @json_decode($line, true);
    if ($entry === null) {
      continue;
    }

    // Apply result filter
    if ($result !== 'all' && isset($entry['result']) && $entry['result'] !== $result) {
      continue;
    }

    // Apply text filter
    if (!empty($filter)) {
      $filter_lower = strtolower($filter);
      $searchable = strtolower(
        ($entry['action'] ?? '') . ' ' .
        ($entry['actor'] ?? '') . ' ' .
        ($entry['target'] ?? '') . ' ' .
        ($entry['details'] ?? '')
      );

      if (strpos($searchable, $filter_lower) === false) {
        continue;
      }
    }

    $count++;
  }

  return $count;
}

/**
 * Clean up old audit log entries based on retention policy
 *
 * @return int Number of entries removed
 */
function audit_cleanup_old_entries() {
  global $AUDIT_ENABLED, $AUDIT_LOG_FILE, $AUDIT_LOG_RETENTION_DAYS, $log_prefix;

  if (!$AUDIT_ENABLED) {
    return 0;
  }

  // Skip cleanup if using STDOUT (logs managed by Docker)
  if ($AUDIT_LOG_FILE === 'stdout' || $AUDIT_LOG_FILE === 'php://stdout' ||
      $AUDIT_LOG_FILE === 'stderr' || $AUDIT_LOG_FILE === 'php://stderr') {
    return 0;
  }

  if (!file_exists($AUDIT_LOG_FILE)) {
    return 0;
  }

  $log_contents = @file_get_contents($AUDIT_LOG_FILE);
  if ($log_contents === false) {
    return 0;
  }

  $lines = explode("\n", trim($log_contents));
  $kept_lines = array();
  $removed_count = 0;

  $cutoff_timestamp = strtotime("-{$AUDIT_LOG_RETENTION_DAYS} days");

  foreach ($lines as $line) {
    if (empty($line)) {
      continue;
    }

    $entry = @json_decode($line, true);
    if ($entry === null) {
      // Keep malformed entries to avoid data loss
      $kept_lines[] = $line;
      continue;
    }

    // Parse timestamp
    $entry_timestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : null;

    if ($entry_timestamp === null || $entry_timestamp >= $cutoff_timestamp) {
      // Keep entry (within retention period or unparseable)
      $kept_lines[] = $line;
    } else {
      // Remove entry (too old)
      $removed_count++;
    }
  }

  // Write back cleaned log
  if ($removed_count > 0) {
    $new_contents = implode("\n", $kept_lines) . "\n";
    $result = @file_put_contents($AUDIT_LOG_FILE, $new_contents, LOCK_EX);

    if ($result === false) {
      error_log("$log_prefix Failed to write cleaned audit log");
      return 0;
    }

    error_log("$log_prefix Audit cleanup: removed $removed_count entries older than $AUDIT_LOG_RETENTION_DAYS days");
  }

  return $removed_count;
}

/**
 * Export audit log entries to CSV format
 *
 * @param string $filter    Optional filter
 * @param string $result    Optional result filter
 * @return string           CSV content
 */
function audit_export_csv($filter = '', $result = 'all') {
  $entries = audit_read_log(999999, 0, $filter, $result); // Get all matching entries

  // CSV header
  $csv = "Timestamp,Actor,IP Address,Action,Target,Result,Details\n";

  // CSV rows
  foreach ($entries as $entry) {
    $row = array(
      $entry['timestamp'] ?? '',
      $entry['actor'] ?? '',
      $entry['ip'] ?? '',
      $entry['action'] ?? '',
      $entry['target'] ?? '',
      $entry['result'] ?? '',
      $entry['details'] ?? ''
    );

    // Escape values for CSV
    $row = array_map(function($val) {
      // Escape quotes and wrap in quotes if contains comma, quote, or newline
      if (strpos($val, ',') !== false || strpos($val, '"') !== false || strpos($val, "\n") !== false) {
        return '"' . str_replace('"', '""', $val) . '"';
      }
      return $val;
    }, $row);

    $csv .= implode(',', $row) . "\n";
  }

  return $csv;
}

?>
