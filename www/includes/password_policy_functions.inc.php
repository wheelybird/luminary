<?php

/**
 * Password Policy Functions
 *
 * Provides functions for enforcing password complexity, history, and expiry policies.
 *
 * REQUIREMENTS:
 * - Password history and expiry features require the OpenLDAP ppolicy overlay
 * - ppolicy overlay provides operational attributes: pwdChangedTime, pwdHistory
 * - Complexity validation works without ppolicy
 */

/**
 * Check if ppolicy overlay is available
 * Tests by checking if pwdChangedTime attribute is accessible
 *
 * @param resource $ldap_connection  LDAP connection
 * @return bool                      True if ppolicy is available
 */
function password_policy_ppolicy_available($ldap_connection) {
  global $LDAP;
  static $cached_result = null;

  if ($cached_result !== null) {
    return $cached_result;
  }

  // Try to read the root DSE to check for ppolicy overlay
  $search = @ldap_read($ldap_connection, '', '(objectClass=*)', array('supportedControl'));

  if (!$search) {
    $cached_result = false;
    return false;
  }

  $entry = ldap_get_entries($ldap_connection, $search);

  if ($entry['count'] == 0) {
    $cached_result = false;
    return false;
  }

  // Check for ppolicy control OID (1.3.6.1.4.1.42.2.27.8.5.1)
  if (isset($entry[0]['supportedcontrol'])) {
    for ($i = 0; $i < $entry[0]['supportedcontrol']['count']; $i++) {
      if ($entry[0]['supportedcontrol'][$i] == '1.3.6.1.4.1.42.2.27.8.5.1') {
        $cached_result = true;
        return true;
      }
    }
  }

  $cached_result = false;
  return false;
}

/**
 * Validate password against policy requirements
 *
 * @param string $password           The password to validate
 * @param array  $validation_errors  Array to store validation error messages (passed by reference)
 * @return bool                      True if password meets all requirements
 */
function password_policy_validate($password, &$validation_errors = array()) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_MIN_LENGTH, $PASSWORD_REQUIRE_UPPERCASE;
  global $PASSWORD_REQUIRE_LOWERCASE, $PASSWORD_REQUIRE_NUMBERS, $PASSWORD_REQUIRE_SPECIAL;
  global $PASSWORD_MIN_SCORE;

  $validation_errors = array();

  // If password policy is disabled, always pass
  if (!$PASSWORD_POLICY_ENABLED) {
    return true;
  }

  $valid = true;

  // Check minimum length
  if (strlen($password) < $PASSWORD_MIN_LENGTH) {
    $validation_errors[] = "Password must be at least {$PASSWORD_MIN_LENGTH} characters long";
    $valid = false;
  }

  // Check uppercase requirement
  if ($PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
    $validation_errors[] = "Password must contain at least one uppercase letter";
    $valid = false;
  }

  // Check lowercase requirement
  if ($PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
    $validation_errors[] = "Password must contain at least one lowercase letter";
    $valid = false;
  }

  // Check numbers requirement
  if ($PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
    $validation_errors[] = "Password must contain at least one number";
    $valid = false;
  }

  // Check special characters requirement
  if ($PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^a-zA-Z0-9]/', $password)) {
    $validation_errors[] = "Password must contain at least one special character";
    $valid = false;
  }

  return $valid;
}

/**
 * Get password strength score (0-4)
 * Uses zxcvbn-style scoring
 *
 * @param string $password  The password to score
 * @return int              Score from 0 (weak) to 4 (strong)
 */
function password_policy_get_strength_score($password) {
  $score = 0;

  // Length scoring
  $length = strlen($password);
  if ($length >= 8) $score++;
  if ($length >= 12) $score++;

  // Complexity scoring
  $has_upper = preg_match('/[A-Z]/', $password);
  $has_lower = preg_match('/[a-z]/', $password);
  $has_number = preg_match('/[0-9]/', $password);
  $has_special = preg_match('/[^a-zA-Z0-9]/', $password);

  $complexity_count = $has_upper + $has_lower + $has_number + $has_special;
  if ($complexity_count >= 3) $score++;
  if ($complexity_count == 4) $score++;

  return min(4, $score);
}

/**
 * Check if password meets minimum strength score requirement
 *
 * @param string $password  The password to check
 * @param int    $score     Password strength score (pass by reference)
 * @return bool             True if meets minimum score
 */
function password_policy_check_strength($password, &$score = null) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_MIN_SCORE;

  $score = password_policy_get_strength_score($password);

  if (!$PASSWORD_POLICY_ENABLED) {
    return true;
  }

  return ($score >= $PASSWORD_MIN_SCORE);
}

/**
 * Store password in history for a user
 *
 * Note: With ppolicy overlay, pwdHistory is automatically maintained
 * This function is a no-op when ppolicy is available
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @param string   $password_hash    Hashed password
 * @return bool                      True on success
 */
function password_policy_add_to_history($ldap_connection, $user_dn, $password_hash) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_HISTORY_COUNT, $log_prefix;

  if (!$PASSWORD_POLICY_ENABLED || $PASSWORD_HISTORY_COUNT <= 0) {
    return true;
  }

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    error_log("$log_prefix Password history requires ppolicy overlay - feature disabled");
    return true; // Silently skip if not available
  }

  // With ppolicy overlay, pwdHistory is automatically maintained by the overlay
  // when passwords are changed. We don't need to (and can't) manually set it.
  // This is an operational attribute managed by the ppolicy overlay.
  return true;
}

/**
 * Get password history for a user
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @return array                     Array of password hashes
 */
function password_policy_get_history($ldap_connection, $user_dn) {
  $search = @ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('pwdHistory'));

  if (!$search) {
    return array();
  }

  $entry = ldap_get_entries($ldap_connection, $search);

  if ($entry['count'] == 0 || !isset($entry[0]['pwdhistory'])) {
    return array();
  }

  $history = array();
  for ($i = 0; $i < $entry[0]['pwdhistory']['count']; $i++) {
    $history[] = $entry[0]['pwdhistory'][$i];
  }

  return $history;
}

/**
 * Check if password was used recently (in history)
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @param string   $plaintext_password  New password to check
 * @return bool                      True if password is in history (should be rejected)
 */
function password_policy_check_history($ldap_connection, $user_dn, $plaintext_password) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_HISTORY_COUNT;

  if (!$PASSWORD_POLICY_ENABLED || $PASSWORD_HISTORY_COUNT <= 0) {
    return false; // Not in history
  }

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    return false; // Can't check history without ppolicy
  }

  $history = password_policy_get_history($ldap_connection, $user_dn);

  foreach ($history as $old_hash) {
    // Check if password matches any in history
    if (password_verify_ldap_hash($plaintext_password, $old_hash)) {
      return true; // Found in history
    }
  }

  return false; // Not in history
}

/**
 * Verify password against LDAP hash
 *
 * @param string $password  Plaintext password
 * @param string $hash      LDAP hash (e.g., {SSHA}...)
 * @return bool             True if password matches
 */
function password_verify_ldap_hash($password, $hash) {
  // Extract hash method
  if (preg_match('/^\{(\w+)\}(.+)$/', $hash, $matches)) {
    $method = $matches[1];
    $hash_data = $matches[2];

    switch (strtoupper($method)) {
      case 'SSHA':
      case 'SSHA256':
      case 'SSHA512':
        // Salted SHA - decode and verify
        $decoded = base64_decode($hash_data);
        $hash_length = ($method == 'SSHA') ? 20 : (($method == 'SSHA256') ? 32 : 64);
        $hash_value = substr($decoded, 0, $hash_length);
        $salt = substr($decoded, $hash_length);

        $algo = ($method == 'SSHA') ? 'sha1' : (($method == 'SSHA256') ? 'sha256' : 'sha512');
        $computed = hash($algo, $password . $salt, true);

        return hash_equals($hash_value, $computed);

      case 'MD5':
      case 'SHA':
        // Unsalted hash
        $algo = strtolower($method);
        return hash_equals(base64_decode($hash_data), hash($algo, $password, true));

      default:
        return false;
    }
  }

  return false;
}

/**
 * Set password changed timestamp for a user
 *
 * Note: With ppolicy overlay, pwdChangedTime is automatically maintained
 * This function is a no-op when ppolicy is available
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @return bool                      True on success
 */
function password_policy_set_changed_time($ldap_connection, $user_dn) {
  global $log_prefix;

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    return true; // Silently skip if not available
  }

  // With ppolicy overlay, pwdChangedTime is automatically maintained by the overlay
  // when passwords are changed. We don't need to (and can't) manually set it.
  // This is an operational attribute managed by the ppolicy overlay.
  return true;
}

/**
 * Get password changed timestamp for a user
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @return string|null               Timestamp or null if not set
 */
function password_policy_get_changed_time($ldap_connection, $user_dn) {
  $search = @ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('pwdChangedTime'));

  if (!$search) {
    return null;
  }

  $entry = ldap_get_entries($ldap_connection, $search);

  if ($entry['count'] == 0 || !isset($entry[0]['pwdchangedtime'][0])) {
    return null;
  }

  return $entry[0]['pwdchangedtime'][0];
}

/**
 * Check if password has expired
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @param int      $days_remaining   Days until expiry (passed by reference)
 * @return bool                      True if expired
 */
function password_policy_is_expired($ldap_connection, $user_dn, &$days_remaining = null) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_EXPIRY_DAYS;

  $days_remaining = null;

  if (!$PASSWORD_POLICY_ENABLED || $PASSWORD_EXPIRY_DAYS <= 0) {
    return false; // Expiry disabled
  }

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    return false; // Can't check expiry without ppolicy
  }

  $changed_time = password_policy_get_changed_time($ldap_connection, $user_dn);

  if (!$changed_time) {
    // No changed time set - not expired yet (grace period)
    return false;
  }

  // Parse LDAP timestamp (YYYYmmddHHMMSSZ)
  $changed_timestamp = strtotime($changed_time);
  $expiry_timestamp = $changed_timestamp + ($PASSWORD_EXPIRY_DAYS * 86400);
  $now = time();

  $seconds_remaining = $expiry_timestamp - $now;
  $days_remaining = ceil($seconds_remaining / 86400);

  return ($expiry_timestamp < $now);
}

/**
 * Check if password expiry warning should be shown
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @param int      $days_remaining   Days until expiry (passed by reference)
 * @return bool                      True if warning should be shown
 */
function password_policy_should_warn($ldap_connection, $user_dn, &$days_remaining = null) {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_EXPIRY_DAYS, $PASSWORD_EXPIRY_WARNING_DAYS;

  if (!$PASSWORD_POLICY_ENABLED || $PASSWORD_EXPIRY_DAYS <= 0) {
    return false;
  }

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    return false; // Can't check expiry without ppolicy
  }

  if (password_policy_is_expired($ldap_connection, $user_dn, $days_remaining)) {
    return true; // Already expired
  }

  return ($days_remaining !== null && $days_remaining > 0 && $days_remaining <= $PASSWORD_EXPIRY_WARNING_DAYS);
}

/**
 * Change user password using Password Modify Extended Operation
 * This allows ppolicy overlay to properly track password history
 *
 * @param resource $ldap_connection  LDAP connection
 * @param string   $user_dn          User DN
 * @param string   $new_password     New cleartext password
 * @return bool                      True on success
 */
function password_policy_change_password($ldap_connection, $user_dn, $new_password) {
  global $log_prefix;

  // Check if ppolicy overlay is available
  if (!password_policy_ppolicy_available($ldap_connection)) {
    error_log("$log_prefix ppolicy not available, falling back to traditional password change");
    // Fall back to traditional method
    return false;
  }

  error_log("$log_prefix Changing password via extended operation for {$user_dn}");

  // Use Password Modify Extended Operation (RFC 3062)
  // This allows the ppolicy overlay to:
  // 1. Track password history
  // 2. Update pwdChangedTime
  // 3. Enforce password history policy
  $result = @ldap_exop_passwd($ldap_connection, $user_dn, "", $new_password);

  if (!$result) {
    error_log("$log_prefix Failed to change password via exop for {$user_dn}: " . ldap_error($ldap_connection));
    return false;
  }

  error_log("$log_prefix Successfully changed password via extended operation for {$user_dn}");
  return true;
}

/**
 * Self-service password change with ppolicy support
 * Binds as the user and uses Password Modify Extended Operation
 * This allows ppolicy overlay to enforce password history
 *
 * @param string $username       Username (account identifier)
 * @param string $old_password   Current password
 * @param string $new_password   New cleartext password
 * @param array  &$error         Error message if failed (passed by reference)
 * @return bool                  True on success
 */
function password_policy_self_service_change($username, $old_password, $new_password, &$error = null) {
  global $log_prefix, $LDAP, $PPOLICY_ENABLED;

  $error = null;

  // Ppolicy must be enabled
  if (!$PPOLICY_ENABLED) {
    error_log("$log_prefix ppolicy password change attempted but ppolicy not enabled");
    $error = "SYSTEM_ERROR";
    return false;
  }

  // Find user DN - this also establishes and validates the LDAP connection
  $admin_ldap = open_ldap_connection();
  if (!$admin_ldap) {
    error_log("$log_prefix ppolicy password change: failed to connect to LDAP as admin");
    $error = "SYSTEM_ERROR";
    return false;
  }

  // SECURITY: Only send cleartext password if using TLS
  // Check the actual connection type (set by open_ldap_connection)
  $connection_type = isset($LDAP['connection_type']) ? $LDAP['connection_type'] : 'plain';

  if ($connection_type != 'StartTLS' && $connection_type != 'LDAPS') {
    error_log("$log_prefix ppolicy password change requires STARTTLS or LDAPS - cleartext password would be sent insecurely. Current connection type: " . $connection_type);
    $error = "SYSTEM_ERROR";
    ldap_close($admin_ldap);
    return false;
  }

  $ldap_search_query = "{$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER);
  $ldap_search = @ldap_search($admin_ldap, $LDAP['user_dn'], $ldap_search_query, array('dn'));

  if (!$ldap_search) {
    error_log("$log_prefix ppolicy password change: user {$username} not found");
    $error = "SYSTEM_ERROR";
    ldap_close($admin_ldap);
    return false;
  }

  $result = @ldap_get_entries($admin_ldap, $ldap_search);
  if ($result["count"] != 1) {
    error_log("$log_prefix ppolicy password change: user {$username} not found or multiple entries");
    $error = "SYSTEM_ERROR";
    ldap_close($admin_ldap);
    return false;
  }

  $user_dn = $result[0]['dn'];
  ldap_close($admin_ldap);

  // Open new connection and bind as the user
  // This is REQUIRED for ppolicy to enforce password history
  $ldap_uri = $LDAP['uri'];

  // Set TLS certificate options same as open_ldap_connection()
  if ($LDAP['ignore_cert_errors'] == TRUE) {
    putenv('LDAPTLS_REQCERT=never');
  }

  $user_ldap = ldap_connect($ldap_uri);
  if (!$user_ldap) {
    error_log("$log_prefix ppolicy password change: failed to connect to LDAP for user bind");
    $error = "SYSTEM_ERROR";
    return false;
  }

  ldap_set_option($user_ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
  ldap_set_option($user_ldap, LDAP_OPT_REFERRALS, 0);

  // Start TLS if using ldap:// (not ldaps://)
  // This matches the logic in open_ldap_connection()
  if (!preg_match("/^ldaps:/", $ldap_uri)) {
    if (!@ldap_start_tls($user_ldap)) {
      error_log("$log_prefix ppolicy password change: failed to start TLS on user connection");
      $error = "SYSTEM_ERROR";
      ldap_close($user_ldap);
      return false;
    }
  }

  // Bind as the user with their current password
  if (!@ldap_bind($user_ldap, $user_dn, $old_password)) {
    error_log("$log_prefix Failed to bind as {$user_dn} for password change (invalid current password)");
    $error = "Invalid current password";
    ldap_close($user_ldap);
    return false;
  }

  error_log("$log_prefix Self-service password change for {$username} using ppolicy");

  // Use Password Modify Extended Operation
  // The old password parameter must be the empty string when bound as the user
  $result = @ldap_exop_passwd($user_ldap, $user_dn, $old_password, $new_password);

  if (!$result) {
    $ldap_error = ldap_error($user_ldap);

    // Get detailed diagnostic message from LDAP (includes ppolicy details)
    $diagnostic_msg = '';
    ldap_get_option($user_ldap, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic_msg);

    // Use diagnostic message if available, otherwise use basic error
    $error = !empty($diagnostic_msg) ? $diagnostic_msg : $ldap_error;

    error_log("$log_prefix ppolicy password change failed for {$user_dn}: {$ldap_error}" . (!empty($diagnostic_msg) ? " - {$diagnostic_msg}" : ""));
    ldap_close($user_ldap);
    return false;
  }

  error_log("$log_prefix Successfully changed password via ppolicy for {$username}");
  ldap_close($user_ldap);
  return true;
}

/**
 * Get policy requirements as human-readable text
 *
 * @return array  Array of requirement strings
 */
function password_policy_get_requirements() {
  global $PASSWORD_POLICY_ENABLED, $PASSWORD_MIN_LENGTH, $PASSWORD_REQUIRE_UPPERCASE;
  global $PASSWORD_REQUIRE_LOWERCASE, $PASSWORD_REQUIRE_NUMBERS, $PASSWORD_REQUIRE_SPECIAL;
  global $PASSWORD_MIN_SCORE;

  if (!$PASSWORD_POLICY_ENABLED) {
    return array();
  }

  $requirements = array();

  if ($PASSWORD_MIN_LENGTH > 0) {
    $requirements[] = "At least {$PASSWORD_MIN_LENGTH} characters";
  }

  if ($PASSWORD_REQUIRE_UPPERCASE) {
    $requirements[] = "At least one uppercase letter";
  }

  if ($PASSWORD_REQUIRE_LOWERCASE) {
    $requirements[] = "At least one lowercase letter";
  }

  if ($PASSWORD_REQUIRE_NUMBERS) {
    $requirements[] = "At least one number";
  }

  if ($PASSWORD_REQUIRE_SPECIAL) {
    $requirements[] = "At least one special character";
  }

  if ($PASSWORD_MIN_SCORE > 0) {
    $score_names = array('very weak', 'weak', 'fair', 'strong', 'very strong');
    $requirements[] = "Minimum strength: " . $score_names[$PASSWORD_MIN_SCORE];
  }

  return $requirements;
}

?>
