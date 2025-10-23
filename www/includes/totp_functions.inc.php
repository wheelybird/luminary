<?php

/**
 * totp_functions.inc.php
 *
 * PHP library for TOTP/MFA functionality
 * Provides secret generation, QR code creation, and OTP validation
 */

/**
 * Generate a random Base32-encoded TOTP secret
 *
 * @param int $length Length of the secret in bytes (default: 20 for 160 bits)
 * @return string Base32-encoded secret
 */
function totp_generate_secret($length = 20) {
  $random_bytes = random_bytes($length);
  return totp_base32_encode($random_bytes);
}

/**
 * Encode data to Base32
 *
 * @param string $data Binary data to encode
 * @return string Base32-encoded string
 */
function totp_base32_encode($data) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $output = '';
  $v = 0;
  $vbits = 0;

  for ($i = 0, $j = strlen($data); $i < $j; $i++) {
    $v = ($v << 8) | ord($data[$i]);
    $vbits += 8;

    while ($vbits >= 5) {
      $vbits -= 5;
      $output .= $alphabet[($v >> $vbits) & 0x1f];
    }
  }

  if ($vbits > 0) {
    $output .= $alphabet[($v << (5 - $vbits)) & 0x1f];
  }

  return $output;
}

/**
 * Decode Base32 data
 *
 * @param string $data Base32-encoded string
 * @return string Binary data
 */
function totp_base32_decode($data) {
  $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  $output = '';
  $v = 0;
  $vbits = 0;

  for ($i = 0, $j = strlen($data); $i < $j; $i++) {
    $v = ($v << 5) | strpos($alphabet, $data[$i]);
    $vbits += 5;

    if ($vbits >= 8) {
      $vbits -= 8;
      $output .= chr(($v >> $vbits) & 0xff);
    }
  }

  return $output;
}

/**
 * Generate a TOTP code for a given secret and time
 *
 * @param string $secret Base32-encoded secret
 * @param int|null $time Unix timestamp (default: current time)
 * @param int $time_step Time step in seconds (default: 30)
 * @param int $digits Number of digits in code (default: 6)
 * @return string|false TOTP code or false on error
 */
function totp_generate_code($secret, $time = null, $time_step = 30, $digits = 6) {
  if ($time === null) {
    $time = time();
  }

  $key = totp_base32_decode($secret);
  if (strlen($key) < 1) {
    return false;
  }

  $time_counter = floor($time / $time_step);
  $time_bytes = pack('N*', 0) . pack('N*', $time_counter);

  $hash = hash_hmac('sha1', $time_bytes, $key, true);

  $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
  $truncated_hash = unpack('N', substr($hash, $offset, 4))[1];
  $code = ($truncated_hash & 0x7fffffff) % pow(10, $digits);

  return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Validate a TOTP code
 *
 * @param string $secret Base32-encoded secret
 * @param string $code TOTP code to validate
 * @param int $window Number of time steps to check before/after current time (default: 1)
 * @param int $time_step Time step in seconds (default: 30)
 * @return bool True if code is valid, false otherwise
 */
function totp_validate_code($secret, $code, $window = 1, $time_step = 30) {
  $time = time();

  for ($i = -$window; $i <= $window; $i++) {
    $test_time = $time + ($i * $time_step);
    $test_code = totp_generate_code($secret, $test_time, $time_step);

    if ($test_code === $code) {
      return true;
    }
  }

  return false;
}

/**
 * Generate a QR code URL for Google Authenticator
 *
 * @param string $secret Base32-encoded secret
 * @param string $label User identifier (e.g., email or username)
 * @param string $issuer Organisation name
 * @return string QR code URL for otpauth:// scheme
 */
function totp_get_qr_code_url($secret, $label, $issuer = 'LDAP') {
  $otpauth_url = 'otpauth://totp/'
    . rawurlencode($issuer) . ':' . rawurlencode($label)
    . '?secret=' . $secret
    . '&issuer=' . rawurlencode($issuer)
    . '&algorithm=SHA1'
    . '&digits=6'
    . '&period=30';

  return $otpauth_url;
}

/**
 * Get QR code data for client-side rendering
 * Returns the otpauth URL for JavaScript QR code generation
 *
 * @param string $otpauth_url OTP Auth URL
 * @param int $size QR code size (unused, kept for compatibility)
 * @return string OTP Auth URL for JS rendering
 */
function totp_get_qr_code_image_url($otpauth_url, $size = 200) {
  // Return the URL itself - will be rendered client-side with JavaScript
  return $otpauth_url;
}

/**
 * Generate backup/scratch codes
 *
 * @param int $count Number of codes to generate (default: 10)
 * @param int $length Length of each code (default: 8)
 * @return array Array of backup codes
 */
function totp_generate_backup_codes($count = 10, $length = 8) {
  $codes = array();

  for ($i = 0; $i < $count; $i++) {
    $code = '';
    for ($j = 0; $j < $length; $j++) {
      $code .= random_int(0, 9);
    }
    $codes[] = $code;
  }

  return $codes;
}

/**
 * Format backup codes for display (groups of 4 digits)
 *
 * @param array $codes Array of backup codes
 * @return array Array of formatted codes
 */
function totp_format_backup_codes($codes) {
  $formatted = array();

  foreach ($codes as $code) {
    $formatted[] = rtrim(chunk_split($code, 4, '-'), '-');
  }

  return $formatted;
}

/**
 * Get TOTP status from LDAP
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $user_dn User DN
 * @return string|null TOTP status or null if not set
 */
function totp_get_status($ldap_connection, $user_dn) {
  global $TOTP_ATTRS;

  $status_attr = $TOTP_ATTRS['status'];
  $attributes = array($status_attr);
  $search = ldap_read($ldap_connection, $user_dn, '(objectClass=*)', $attributes);

  if (!$search) {
    return null;
  }

  $entry = ldap_get_entries($ldap_connection, $search);
  $status_attr_lower = strtolower($status_attr);

  if ($entry['count'] > 0 && isset($entry[0][$status_attr_lower][0])) {
    return $entry[0][$status_attr_lower][0];
  }

  return null;
}

/**
 * Get count of remaining backup codes from LDAP
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $user_dn User DN
 * @return int Number of backup codes remaining
 */
function totp_get_backup_code_count($ldap_connection, $user_dn) {
  global $TOTP_ATTRS;

  $scratch_attr = $TOTP_ATTRS['scratch_codes'];
  $attributes = array($scratch_attr);
  $search = ldap_read($ldap_connection, $user_dn, '(objectClass=*)', $attributes);

  if (!$search) {
    return 0;
  }

  $entry = ldap_get_entries($ldap_connection, $search);
  $scratch_attr_lower = strtolower($scratch_attr);

  if ($entry['count'] > 0 && isset($entry[0][$scratch_attr_lower]['count'])) {
    return (int)$entry[0][$scratch_attr_lower]['count'];
  }

  return 0;
}

/**
 * Set TOTP secret in LDAP
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $user_dn User DN
 * @param string $secret Base32-encoded secret
 * @param array $backup_codes Optional array of backup codes
 * @return bool True on success, false on failure
 */
function totp_set_secret($ldap_connection, $user_dn, $secret, $backup_codes = array()) {
  global $LDAP, $TOTP_ATTRS, $MFA_SCHEMA_OK;

  // Check if schema is available
  if (!$MFA_SCHEMA_OK) {
    error_log("totp_set_secret: Cannot set TOTP secret - schema not available");
    return false;
  }

  $objectclass = $TOTP_ATTRS['objectclass'];

  // Add TOTP object class if not present
  $search = ldap_read($ldap_connection, $user_dn, '(objectClass=*)', array('objectClass'));
  if ($search) {
    $entry = ldap_get_entries($ldap_connection, $search);
    $object_classes = array();

    for ($i = 0; $i < $entry[0]['objectclass']['count']; $i++) {
      $object_classes[] = $entry[0]['objectclass'][$i];
    }

    if (!in_array($objectclass, array_map('strtolower', $object_classes))) {
      $oc_mod = array('objectClass' => $objectclass);
      if (!@ldap_mod_add($ldap_connection, $user_dn, $oc_mod)) {
        // If add fails, object class might already exist - continue anyway
      }
    }
  }

  // Set TOTP attributes
  $modifications = array();
  $modifications[$TOTP_ATTRS['secret']] = $secret;
  $modifications[$TOTP_ATTRS['enrolled_date']] = gmdate('YmdHis') . 'Z';
  $modifications[$TOTP_ATTRS['status']] = 'active';

  // Set backup codes if provided
  if (count($backup_codes) > 0) {
    $modifications[$TOTP_ATTRS['scratch_codes']] = $backup_codes;
  }

  return ldap_mod_replace($ldap_connection, $user_dn, $modifications);
}

/**
 * Remove TOTP configuration from LDAP
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $user_dn User DN
 * @return bool True on success, false on failure
 */
function totp_disable($ldap_connection, $user_dn) {
  global $TOTP_ATTRS, $MFA_SCHEMA_OK;

  // Check if schema is available
  if (!$MFA_SCHEMA_OK) {
    error_log("totp_disable: Cannot disable TOTP - schema not available");
    return false;
  }

  $modifications = array(
    $TOTP_ATTRS['secret'] => array(),
    $TOTP_ATTRS['scratch_codes'] => array(),
    $TOTP_ATTRS['status'] => 'disabled',
  );

  return ldap_mod_replace($ldap_connection, $user_dn, $modifications);
}

/**
 * Check if user is in an MFA-required group
 *
 * @param resource $ldap_connection LDAP connection resource
 * @param string $username Username to check
 * @param array $mfa_required_groups Array of group names requiring MFA
 * @return bool True if user is in an MFA-required group
 */
function totp_user_requires_mfa($ldap_connection, $username, $mfa_required_groups) {
  if (empty($mfa_required_groups)) {
    return false;
  }

  // Get user's groups using existing function that handles RFC2307bis
  $user_groups = ldap_user_group_membership($ldap_connection, $username);

  // Check if user is member of any MFA-required group
  foreach ($mfa_required_groups as $required_group) {
    if (in_array($required_group, $user_groups)) {
      return true;
    }
  }

  return false;
}

/**
 * Calculate days remaining in grace period
 *
 * @param string $enrolled_date LDAP timestamp (YmdHisZ format)
 * @param int $grace_period_days Grace period length in days
 * @return int Days remaining (negative if expired)
 */
function totp_grace_period_remaining($enrolled_date, $grace_period_days) {
  if (empty($enrolled_date)) {
    return $grace_period_days;
  }

  // Parse LDAP timestamp
  $year = substr($enrolled_date, 0, 4);
  $month = substr($enrolled_date, 4, 2);
  $day = substr($enrolled_date, 6, 2);
  $hour = substr($enrolled_date, 8, 2);
  $minute = substr($enrolled_date, 10, 2);
  $second = substr($enrolled_date, 12, 2);

  $enrolled_time = gmmktime($hour, $minute, $second, $month, $day, $year);
  $expiry_time = $enrolled_time + ($grace_period_days * 86400);
  $now = time();

  $seconds_remaining = $expiry_time - $now;
  return ceil($seconds_remaining / 86400);
}

/**
 * Get comprehensive MFA status for a user
 *
 * Returns an array with MFA status information including whether MFA is required,
 * current status, and grace period information.
 *
 * @param resource $ldap_connection LDAP connection
 * @param string $username Username to check
 * @param array $mfa_required_groups Array of group names requiring MFA
 * @param int $grace_period_days Grace period length in days
 * @return array Status array with keys: requires_mfa, status, enrolled_date, days_remaining, needs_setup
 */
function totp_get_user_mfa_status($ldap_connection, $username, $mfa_required_groups = array(), $grace_period_days = 7) {
  global $LDAP, $TOTP_ATTRS;

  $status_attr = $TOTP_ATTRS['status'];
  $enrolled_attr = $TOTP_ATTRS['enrolled_date'];
  $status_attr_lower = strtolower($status_attr);
  $enrolled_attr_lower = strtolower($enrolled_attr);

  $status_data = array(
    'requires_mfa' => false,
    'status' => 'none',
    'enrolled_date' => null,
    'days_remaining' => null,
    'needs_setup' => false
  );

  // Check if user is in an MFA-required group
  if (!empty($mfa_required_groups)) {
    $status_data['requires_mfa'] = totp_user_requires_mfa($ldap_connection, $username, $mfa_required_groups);
  }

  // Get user's current MFA attributes
  $user_filter = "({$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")";
  $user_search = ldap_search($ldap_connection, $LDAP['user_dn'], $user_filter, array($status_attr, $enrolled_attr));

  if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
      $status_data['status'] = isset($user_entry[0][$status_attr_lower][0]) ? $user_entry[0][$status_attr_lower][0] : 'none';
      $status_data['enrolled_date'] = isset($user_entry[0][$enrolled_attr_lower][0]) ? $user_entry[0][$enrolled_attr_lower][0] : null;

      if ($status_data['enrolled_date']) {
        $status_data['days_remaining'] = totp_grace_period_remaining($status_data['enrolled_date'], $grace_period_days);
      }
    }
  }

  // Determine if setup is needed
  $status_data['needs_setup'] = $status_data['requires_mfa'] &&
                                  ($status_data['status'] == 'none' || $status_data['status'] == 'pending');

  return $status_data;
}

?>
