<?php

/**
 * Group MFA Handler
 * Processes MFA settings updates for groups
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

// Handle MFA form submission
if (isset($_POST["submit_mfa"]) && $MFA_FEATURE_ENABLED) {

  // Handle MFA settings update
  $group_dn = "{$LDAP['group_attribute']}=$group_cn,{$LDAP['group_dn']}";

  // Get attribute names from config
  $mfa_objectclass = $GROUP_MFA_ATTRS['objectclass'];
  $mfa_required_attr = $GROUP_MFA_ATTRS['required'];
  $mfa_grace_period_attr = $GROUP_MFA_ATTRS['grace_period'];

  // Get current object classes
  $current_objectclasses = array();
  if (isset($this_group[0]['objectclass'])) {
    foreach ($this_group[0]['objectclass'] as $index => $oc) {
      if ($index !== 'count') {
        $current_objectclasses[] = $oc;
      }
    }
  }

  // Check if MFA object class exists
  $has_mfa_oc = false;
  foreach ($current_objectclasses as $oc) {
    if (strcasecmp($oc, $mfa_objectclass) == 0) {
      $has_mfa_oc = true;
      break;
    }
  }

  // Get form values
  $mfa_required = isset($_POST['mfa_required']) ? 'TRUE' : 'FALSE';
  $mfa_grace_period = isset($_POST['mfa_grace_period']) ? intval($_POST['mfa_grace_period']) : 7;

  // Prepare modifications
  $modifications = array();

  // Add MFA object class if not present and MFA is being enabled
  if (!$has_mfa_oc && $mfa_required == 'TRUE') {
    $current_objectclasses[] = $mfa_objectclass;
    $modifications['objectClass'] = $current_objectclasses;
  }

  // Set MFA attributes
  $modifications[$mfa_required_attr] = $mfa_required;
  $modifications[$mfa_grace_period_attr] = strval($mfa_grace_period);

  // Apply modifications
  $ldap_connection = open_ldap_connection();
  $result = @ ldap_mod_replace($ldap_connection, $group_dn, $modifications);

  if ($result) {
    // Audit log MFA settings update
    $mfa_details = "MFA required: {$mfa_required}, Grace period: {$mfa_grace_period} days";
    audit_log('group_mfa_updated', $group_cn, $mfa_details, 'success', $USER_ID);

    $message = "MFA settings for group '$group_cn' have been updated.";
    render_alert_banner($message, "success", 5000);
    // Reload group data
    $this_group = ldap_get_group_entry($ldap_connection, $group_cn);
  } else {
    // Audit log failed MFA update
    $ldap_error = ldap_error($ldap_connection);
    audit_log('group_mfa_update_failure', $group_cn, "Failed to update MFA settings: {$ldap_error}", 'failure', $USER_ID);

    $message = "There was a problem updating MFA settings. LDAP error: $ldap_error";
    render_alert_banner($message, "danger", 10000);
  }

}

?>
