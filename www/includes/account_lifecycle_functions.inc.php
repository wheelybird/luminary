<?php
/**
 * Account Lifecycle Functions
 *
 * Provides account expiration and lifecycle management using standard LDAP schemas:
 * - shadowAccount (RFC 2307) for account expiration
 * - ppolicy overlay for account locking
 *
 * No custom schema extensions required.
 * No background jobs/cronjobs required - all enforcement at login time.
 *
 * @uses shadowExpire - Account expiration date (days since Unix epoch)
 * @uses shadowWarning - Days before expiry to warn user
 * @uses pwdAccountLockedTime - ppolicy account lock timestamp (if enabled)
 */

/**
 * Get account expiration date from shadowExpire attribute
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @return int|null Shadow expire value (days since epoch) or null if not set
 */
function account_lifecycle_get_expiry_days($ldap_connection, $user_dn) {
    // Search for shadowExpire attribute
    $search = ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('shadowExpire'));

    if (!$search) {
        return null;
    }

    $entry = ldap_get_entries($ldap_connection, $search);

    if ($entry['count'] > 0 && isset($entry[0]['shadowexpire'][0])) {
        return (int)$entry[0]['shadowexpire'][0];
    }

    return null;
}

/**
 * Check if account is expired
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @param int &$days_remaining Output parameter - days remaining until expiry (negative if expired)
 * @return bool True if account is expired, false otherwise
 */
function account_lifecycle_is_expired($ldap_connection, $user_dn, &$days_remaining = null) {
    $shadow_expire = account_lifecycle_get_expiry_days($ldap_connection, $user_dn);

    if ($shadow_expire === null) {
        // No expiration set - account never expires
        $days_remaining = null;
        return false;
    }

    // shadowExpire is days since Unix epoch (1970-01-01)
    // Current date in days since epoch
    $current_days = floor(time() / 86400);

    // Calculate days remaining
    $days_remaining = $shadow_expire - $current_days;

    // Account is expired if shadowExpire is in the past
    return ($days_remaining < 0);
}

/**
 * Check if user should be warned about approaching expiration
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @param int &$days_remaining Output parameter - days remaining until expiry
 * @return bool True if should warn, false otherwise
 */
function account_lifecycle_should_warn($ldap_connection, $user_dn, &$days_remaining = null) {
    global $ACCOUNT_EXPIRY_WARNING_DAYS;

    // Check if account is expired first
    $is_expired = account_lifecycle_is_expired($ldap_connection, $user_dn, $days_remaining);

    if ($is_expired) {
        // Already expired - no need for warning
        return false;
    }

    if ($days_remaining === null) {
        // No expiration set - no warning needed
        return false;
    }

    // Warn if within warning threshold
    return ($days_remaining > 0 && $days_remaining <= $ACCOUNT_EXPIRY_WARNING_DAYS);
}

/**
 * Get account expiration date as Unix timestamp
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @return int|null Unix timestamp of expiration date, or null if not set
 */
function account_lifecycle_get_expiry_timestamp($ldap_connection, $user_dn) {
    $shadow_expire = account_lifecycle_get_expiry_days($ldap_connection, $user_dn);

    if ($shadow_expire === null) {
        return null;
    }

    // Convert days since epoch to Unix timestamp
    return $shadow_expire * 86400;
}

/**
 * Get account expiration date as formatted string
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @param string $format PHP date format string (default: 'F j, Y')
 * @return string|null Formatted expiration date, or null if not set
 */
function account_lifecycle_get_expiry_date_formatted($ldap_connection, $user_dn, $format = 'F j, Y') {
    $timestamp = account_lifecycle_get_expiry_timestamp($ldap_connection, $user_dn);

    if ($timestamp === null) {
        return null;
    }

    return date($format, $timestamp);
}

/**
 * Set account expiration date
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @param int|null $expiry_timestamp Unix timestamp of expiration date, or null to remove expiration
 * @return bool True on success, false on failure
 */
function account_lifecycle_set_expiry($ldap_connection, $user_dn, $expiry_timestamp = null) {
    if ($expiry_timestamp === null) {
        // Remove expiration - delete shadowExpire attribute
        $modification = array('shadowExpire' => array());
        return @ldap_mod_del($ldap_connection, $user_dn, $modification);
    }

    // Convert Unix timestamp to days since epoch
    $shadow_expire = floor($expiry_timestamp / 86400);

    // Check if shadowAccount object class exists
    $search = ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('objectClass'));
    if (!$search) {
        return false;
    }

    $entry = ldap_get_entries($ldap_connection, $search);
    $has_shadow_account = false;

    if ($entry['count'] > 0 && isset($entry[0]['objectclass'])) {
        foreach ($entry[0]['objectclass'] as $key => $class) {
            if ($key !== 'count' && strcasecmp($class, 'shadowAccount') === 0) {
                $has_shadow_account = true;
                break;
            }
        }
    }

    // If shadowAccount object class doesn't exist, add it first
    if (!$has_shadow_account) {
        $add_class = array('objectClass' => 'shadowAccount');
        if (!@ldap_mod_add($ldap_connection, $user_dn, $add_class)) {
            return false;
        }
    }

    // Set or update shadowExpire attribute
    $modification = array('shadowExpire' => (string)$shadow_expire);

    // Try to replace first, if that fails try to add
    if (@ldap_mod_replace($ldap_connection, $user_dn, $modification)) {
        return true;
    }

    return @ldap_mod_add($ldap_connection, $user_dn, $modification);
}

/**
 * Check if account is locked (via ppolicy overlay)
 *
 * Requires PPOLICY_ENABLED and ppolicy overlay configured on LDAP server.
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @return bool True if account is locked, false otherwise
 */
function account_lifecycle_is_locked($ldap_connection, $user_dn) {
    global $PPOLICY_ENABLED;

    if ($PPOLICY_ENABLED != TRUE) {
        // ppolicy not enabled - no lock status available
        return false;
    }

    // Query pwdAccountLockedTime operational attribute
    $search = ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('pwdAccountLockedTime'));

    if (!$search) {
        return false;
    }

    $entry = ldap_get_entries($ldap_connection, $search);

    // If pwdAccountLockedTime is set, account is locked
    if ($entry['count'] > 0 && isset($entry[0]['pwdaccountlockedtime'][0])) {
        return true;
    }

    return false;
}

/**
 * Unlock account (clear pwdAccountLockedTime via ppolicy)
 *
 * Requires PPOLICY_ENABLED and admin privileges.
 *
 * @param resource $ldap_connection Active LDAP connection with admin privileges
 * @param string $user_dn User's distinguished name
 * @return bool True on success, false on failure
 */
function account_lifecycle_unlock($ldap_connection, $user_dn) {
    global $PPOLICY_ENABLED;

    if ($PPOLICY_ENABLED != TRUE) {
        return false;
    }

    // Delete pwdAccountLockedTime attribute to unlock
    $modification = array('pwdAccountLockedTime' => array());
    return @ldap_mod_del($ldap_connection, $user_dn, $modification);
}

/**
 * Get account creation timestamp
 *
 * Uses operational attribute createTimestamp (standard LDAP).
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @return string|null LDAP timestamp string (YYYYMMDDHHMMSSZ) or null if not available
 */
function account_lifecycle_get_create_time($ldap_connection, $user_dn) {
    $search = ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('createTimestamp'));

    if (!$search) {
        return null;
    }

    $entry = ldap_get_entries($ldap_connection, $search);

    if ($entry['count'] > 0 && isset($entry[0]['createtimestamp'][0])) {
        return $entry[0]['createtimestamp'][0];
    }

    return null;
}

/**
 * Get account last modified timestamp
 *
 * Uses operational attribute modifyTimestamp (standard LDAP).
 *
 * @param resource $ldap_connection Active LDAP connection
 * @param string $user_dn User's distinguished name
 * @return string|null LDAP timestamp string (YYYYMMDDHHMMSSZ) or null if not available
 */
function account_lifecycle_get_modify_time($ldap_connection, $user_dn) {
    $search = ldap_read($ldap_connection, $user_dn, "(objectClass=*)", array('modifyTimestamp'));

    if (!$search) {
        return null;
    }

    $entry = ldap_get_entries($ldap_connection, $search);

    if ($entry['count'] > 0 && isset($entry[0]['modifytimestamp'][0])) {
        return $entry[0]['modifytimestamp'][0];
    }

    return null;
}

/**
 * Convert LDAP timestamp to Unix timestamp
 *
 * @param string $ldap_timestamp LDAP timestamp (YYYYMMDDHHMMSSZ format)
 * @return int|null Unix timestamp or null on error
 */
function account_lifecycle_ldap_to_timestamp($ldap_timestamp) {
    if (empty($ldap_timestamp)) {
        return null;
    }

    // Parse LDAP timestamp: YYYYMMDDHHMMSSZ
    $timestamp = strtotime($ldap_timestamp);

    if ($timestamp === false) {
        return null;
    }

    return $timestamp;
}

?>
