<?php

/**
 * User Lifecycle Handler
 * Processes account lifecycle actions (expiry, unlock, etc.)
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

// Handle account expiration updates (admin only)
if (isset($_POST['update_account_expiry']) || isset($_POST['remove_account_expiry'])) {
  include_once "account_lifecycle_functions.inc.php";

  $lifecycle_search = ldap_search($ldap_connection, $LDAP['user_dn'], $ldap_search_query);
  if ($lifecycle_search) {
    $lifecycle_user = ldap_get_entries($ldap_connection, $lifecycle_search);
    if ($lifecycle_user['count'] > 0) {
      $lifecycle_dn = $lifecycle_user[0]['dn'];

      if (isset($_POST['remove_account_expiry'])) {
        // Remove account expiration
        if (account_lifecycle_set_expiry($ldap_connection, $lifecycle_dn, null)) {
          audit_log('account_expiry_removed', $account_identifier, "Admin removed account expiration", 'success', $USER_ID);
          render_alert_banner("Account expiration has been removed. Account will not expire.");
        } else {
          audit_log('account_expiry_remove_failure', $account_identifier, "Failed to remove account expiration", 'failure', $USER_ID);
          render_alert_banner("Failed to remove account expiration. Check the logs for more information.", "danger", 15000);
        }
      } elseif (isset($_POST['account_expiry_date']) && !empty($_POST['account_expiry_date'])) {
        // Set account expiration date
        $expiry_date_input = $_POST['account_expiry_date'];
        $expiry_timestamp = strtotime($expiry_date_input . ' 23:59:59'); // End of day

        if ($expiry_timestamp !== false) {
          if (account_lifecycle_set_expiry($ldap_connection, $lifecycle_dn, $expiry_timestamp)) {
            audit_log('account_expiry_set', $account_identifier, "Admin set account expiration to " . date('Y-m-d', $expiry_timestamp), 'success', $USER_ID);
            render_alert_banner("Account expiration has been set to " . date('F j, Y', $expiry_timestamp) . ".");
          } else {
            audit_log('account_expiry_set_failure', $account_identifier, "Failed to set account expiration", 'failure', $USER_ID);
            render_alert_banner("Failed to set account expiration. Check the logs for more information.", "danger", 15000);
          }
        } else {
          render_alert_banner("Invalid date format. Please use YYYY-MM-DD.", "danger", 15000);
        }
      }
    }
  }
}

// Handle account unlock (admin only)
if (isset($_POST['unlock_account'])) {
  if ($PPOLICY_ENABLED == TRUE) {
    include_once "account_lifecycle_functions.inc.php";

    $unlock_search = ldap_search($ldap_connection, $LDAP['user_dn'], $ldap_search_query);
    if ($unlock_search) {
      $unlock_user = ldap_get_entries($ldap_connection, $unlock_search);
      if ($unlock_user['count'] > 0) {
        $unlock_dn = $unlock_user[0]['dn'];

        if (account_lifecycle_unlock($ldap_connection, $unlock_dn)) {
          audit_log('account_unlocked', $account_identifier, "Admin unlocked account", 'success', $USER_ID);
          render_alert_banner("Account has been unlocked successfully.");
        } else {
          audit_log('account_unlock_failure', $account_identifier, "Failed to unlock account", 'failure', $USER_ID);
          render_alert_banner("Failed to unlock account. Check the logs for more information.", "danger", 15000);
        }
      }
    }
  }
}

// Get account lifecycle information
include_once "account_lifecycle_functions.inc.php";

$account_days_remaining = null;
$account_is_expired = account_lifecycle_is_expired($ldap_connection, $dn, $account_days_remaining);
$account_expiry_date_formatted = account_lifecycle_get_expiry_date_formatted($ldap_connection, $dn, 'F j, Y \a\t g:i A');
$account_expiry_timestamp = account_lifecycle_get_expiry_timestamp($ldap_connection, $dn);
$account_create_time = account_lifecycle_get_create_time($ldap_connection, $dn);
$account_modify_time = account_lifecycle_get_modify_time($ldap_connection, $dn);

// Convert LDAP timestamps to formatted strings
$account_created_formatted = null;
if ($account_create_time) {
  $create_timestamp = account_lifecycle_ldap_to_timestamp($account_create_time);
  if ($create_timestamp) {
    $account_created_formatted = date('F j, Y \a\t g:i A', $create_timestamp);
  }
}

// Determine account status
$account_status_badge = 'bg-success';
$account_status_text = 'Active';
if ($account_is_expired) {
  $account_status_badge = 'bg-danger';
  $account_status_text = 'Expired';
} elseif ($account_days_remaining !== null && $account_days_remaining > 0 && $account_days_remaining <= $ACCOUNT_EXPIRY_WARNING_DAYS) {
  $account_status_badge = 'bg-warning text-dark';
  $account_status_text = 'Expiring Soon';
} elseif ($account_expiry_date_formatted === null) {
  $account_status_text = 'No Expiration';
}

// Check if account is locked (requires ppolicy)
$account_is_locked = false;
if ($PPOLICY_ENABLED == TRUE) {
  $account_is_locked = account_lifecycle_is_locked($ldap_connection, $dn);
  if ($account_is_locked) {
    $account_status_badge = 'bg-danger';
    $account_status_text = 'Locked';
  }
}

?>
