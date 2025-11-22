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
  $debug_info = array();
  $debug_info[] = "MFA save triggered for group: $group_cn";

  $group_dn = "{$LDAP['group_attribute']}=$group_cn,{$LDAP['group_dn']}";
  $debug_info[] = "Group DN: $group_dn";

  // Get attribute names from config
  $mfa_objectclass = $GROUP_MFA_ATTRS['objectclass'];
  $mfa_required_attr = $GROUP_MFA_ATTRS['required'];
  $mfa_grace_period_attr = $GROUP_MFA_ATTRS['grace_period'];

  $debug_info[] = "MFA objectclass: $mfa_objectclass";
  $debug_info[] = "MFA required attr: $mfa_required_attr";
  $debug_info[] = "MFA grace period attr: $mfa_grace_period_attr";

  // Get current object classes
  $current_objectclasses = array();
  if (isset($this_group[0]['objectclass'])) {
    foreach ($this_group[0]['objectclass'] as $index => $oc) {
      if ($index !== 'count') {
        $current_objectclasses[] = $oc;
      }
    }
  }

  $debug_info[] = "Current objectclasses: " . implode(', ', $current_objectclasses);

  // Check if MFA object class exists
  $has_mfa_oc = false;
  foreach ($current_objectclasses as $oc) {
    if (strcasecmp($oc, $mfa_objectclass) == 0) {
      $has_mfa_oc = true;
      break;
    }
  }

  $debug_info[] = "Has MFA objectclass: " . ($has_mfa_oc ? 'yes' : 'no');

  // Get form values
  $mfa_required = isset($_POST['mfa_required']) ? 'TRUE' : 'FALSE';
  $mfa_grace_period = isset($_POST['mfa_grace_period']) ? intval($_POST['mfa_grace_period']) : 7;

  $debug_info[] = "Form mfa_required: $mfa_required";
  $debug_info[] = "Form mfa_grace_period: $mfa_grace_period";

  // Prepare modifications
  $modifications = array();

  // Add MFA object class if not present and MFA is being enabled
  if (!$has_mfa_oc && $mfa_required == 'TRUE') {
    $current_objectclasses[] = $mfa_objectclass;
    $modifications['objectClass'] = $current_objectclasses;
    $debug_info[] = "Adding MFA objectclass to modifications";
  }

  // Set MFA attributes
  $modifications[$mfa_required_attr] = $mfa_required;
  $modifications[$mfa_grace_period_attr] = strval($mfa_grace_period);

  $debug_info[] = "Modifications: objectClass=" . (isset($modifications['objectClass']) ? implode(',', $modifications['objectClass']) : 'not changed') .
                  ", $mfa_required_attr=$mfa_required, $mfa_grace_period_attr=$mfa_grace_period";

  // Apply modifications
  $ldap_connection = open_ldap_connection();
  $result = @ ldap_mod_replace($ldap_connection, $group_dn, $modifications);

  $debug_info[] = "LDAP result: " . ($result ? 'SUCCESS' : 'FAILED');
  $ldap_error = ldap_error($ldap_connection);
  $debug_info[] = "LDAP error/status: " . $ldap_error;

  if ($result) {
    // Audit log MFA settings update
    $mfa_details = "MFA required: {$mfa_required}, Grace period: {$mfa_grace_period} days";
    audit_log('group_mfa_updated', $group_cn, $mfa_details, 'success', $USER_ID);

    $message = "MFA settings for group '$group_cn' have been updated.<br><br><strong>Debug Info:</strong><br>" .
               implode('<br>', $debug_info);
    render_alert_banner($message, "success", 15000);
    // Reload group data
    $this_group = ldap_get_group_entry($ldap_connection, $group_cn);
  } else {
    // Audit log failed MFA update
    audit_log('group_mfa_update_failure', $group_cn, "Failed to update MFA settings: {$ldap_error}", 'failure', $USER_ID);

    $message = "There was a problem updating MFA settings.<br><br><strong>LDAP Error:</strong> $ldap_error<br><br><strong>Debug Info:</strong><br>" .
               implode('<br>', $debug_info);
    render_alert_banner($message, "danger", 20000);
  }

}

?>
