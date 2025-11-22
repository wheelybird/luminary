<?php

/**
 * Group Members Handler
 * Processes group membership and attribute updates
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

if (isset($_POST["update_members"])) {

  $updated_membership = array();

  // Check if membership array exists before iterating (fixes #230 - empty group validation)
  if (isset($_POST['membership']) && is_array($_POST['membership'])) {
    foreach ($_POST['membership'] as $index => $member) {
      if (is_numeric($index)) {
       array_push($updated_membership, trim($member));
      }
    }
  }

  if ($group_cn == $LDAP['admins_group'] and !array_search($USER_ID, $updated_membership)){
    array_push($updated_membership,$USER_ID);
  }

  $members_to_del = array_diff($current_members,$updated_membership);
  $members_to_add = array_diff($updated_membership,$current_members);

  if ($initialise_group == TRUE) {

    $initial_member = array_shift($members_to_add);
    $group_add = ldap_new_group($ldap_connection,$group_cn,$initial_member,$to_update);
    if (!$group_add) {
      // Audit log failed group creation
      audit_log('group_create_failure', $group_cn, "Failed to create group", 'failure', $USER_ID);
      render_alert_banner("There was a problem creating the group.  See the logs for more information.","danger",10000);
      $group_exists = FALSE;
      $new_group = TRUE;
    }
    else {
      // Audit log group creation
      audit_log('group_created', $group_cn, "Group created with initial member: {$initial_member}", 'success', $USER_ID);
      $group_exists = TRUE;
      $new_group = FALSE;
    }

  }

  if ($group_exists == TRUE) {

    if ($initialise_group != TRUE and count($to_update) > 0) {

      if (isset($this_group[0]['objectclass'])) {
        $existing_objectclasses = $this_group[0]['objectclass'];
        unset($existing_objectclasses['count']);
        if ($existing_objectclasses != $LDAP['group_objectclasses']) { $to_update['objectclass'] = $LDAP['group_objectclasses']; }
      }

      $updated_attr = ldap_update_group_attributes($ldap_connection,$group_cn,$to_update);

      if ($updated_attr) {
        // Audit log group attribute update
        $update_fields = array_keys($to_update);
        $update_details = "Updated fields: " . implode(', ', $update_fields);
        audit_log('group_updated', $group_cn, $update_details, 'success', $USER_ID);
        render_alert_banner("The group attributes have been updated.");
      }
      else {
        // Audit log failed update
        audit_log('group_update_failure', $group_cn, "Failed to update group attributes", 'failure', $USER_ID);
        render_alert_banner("There was a problem updating the group attributes.  See the logs for more information.","danger",15000);
      }

    }

    foreach ($members_to_add as $this_member) {
      if (ldap_add_member_to_group($ldap_connection,$group_cn,$this_member)) {
        // Audit log member addition
        audit_log('group_member_added', $group_cn, "User added to group: {$this_member}", 'success', $USER_ID);
      }
    }

    foreach ($members_to_del as $this_member) {
      if (ldap_delete_member_from_group($ldap_connection,$group_cn,$this_member)) {
        // Audit log member removal
        audit_log('group_member_removed', $group_cn, "User removed from group: {$this_member}", 'success', $USER_ID);
      }
    }

    // Reload actual membership from LDAP to ensure accuracy
    $current_members = ldap_get_group_members($ldap_connection,$group_cn);
    $group_members = $current_members;
    $non_members = array_diff($all_people,$group_members);

    $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);
    if ($rfc2307bis_available == TRUE and count($group_members) == 0) {
      render_alert_banner("Groups can't be empty, so the final member hasn't been removed.  You could try deleting the group","danger",15000);
    }
    else {
      render_alert_banner("The group has been {$has_been}.");
    }

  }
  else {

    $group_members = array();
    $non_members = $all_people;

  }

}

?>
