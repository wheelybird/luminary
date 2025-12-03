<?php

/**
 * User Groups Handler
 * Processes group membership updates for users
 */

// This file should only be included, never accessed directly
if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

// Get all groups and current membership
$all_groups = ldap_get_group_list($ldap_connection);
$currently_member_of = ldap_user_group_membership($ldap_connection, $account_identifier);
$not_member_of = array_diff($all_groups, $currently_member_of);

// Handle group membership update
if (isset($_POST["update_member_of"])) {
  $updated_group_membership = array();

  foreach ($_POST as $index => $group) {
    if (is_numeric($index)) {
      array_push($updated_group_membership, $group);
    }
  }

  // Prevent admin from removing themselves from admin group
  if ($USER_ID == $account_identifier and !array_search($USER_ID, $updated_group_membership)) {
    array_push($updated_group_membership, $LDAP["admins_group"]);
  }

  $groups_to_add = array_diff($updated_group_membership, $currently_member_of);
  $groups_to_del = array_diff($currently_member_of, $updated_group_membership);

  foreach ($groups_to_del as $this_group) {
    if (ldap_delete_member_from_group($ldap_connection, $this_group, $account_identifier)) {
      // Audit log group removal
      audit_log('user_removed_from_group', $account_identifier, "Removed from group: {$this_group}", 'success', $USER_ID);
    }
  }

  foreach ($groups_to_add as $this_group) {
    if (ldap_add_member_to_group($ldap_connection, $this_group, $account_identifier)) {
      // Audit log group addition
      audit_log('user_added_to_group', $account_identifier, "Added to group: {$this_group}", 'success', $USER_ID);
    }
  }

  $not_member_of = array_diff($all_groups, $updated_group_membership);
  $member_of = $updated_group_membership;
  render_alert_banner("The group membership has been updated.");
} else {
  $member_of = $currently_member_of;
}

?>
