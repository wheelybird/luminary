<?php

/**
 * User MFA Handler
 * Processes MFA-related actions (backup code regeneration, etc.)
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

// Get MFA status for this user
$user_totp_status = isset($user[0]['totpstatus'][0]) ? $user[0]['totpstatus'][0] : 'none';
$user_backup_code_count = totp_get_backup_code_count($ldap_connection, $dn);
$mfa_result = totp_user_requires_mfa($ldap_connection, $account_identifier, $MFA_REQUIRED_GROUPS);
$user_requires_mfa = $mfa_result['required'];

// Handle backup code regeneration (admin only)
if (isset($_POST['regenerate_backup_codes'])) {
  if (!$MFA_SCHEMA_OK) {
    render_alert_banner("Cannot regenerate backup codes: TOTP schema is not installed in LDAP.", "danger", 15000);
  } else {
    $regenerate_search = ldap_search($ldap_connection, $LDAP['user_dn'], $ldap_search_query);
    if ($regenerate_search) {
      $regenerate_user = ldap_get_entries($ldap_connection, $regenerate_search);
      if ($regenerate_user['count'] > 0) {
        $regenerate_dn = $regenerate_user[0]['dn'];

        // Generate new backup codes
        $new_backup_codes = totp_generate_backup_codes(10, 8);

        // Update LDAP with new codes
        $modifications = array(
          $TOTP_ATTRS['scratch_codes'] => $new_backup_codes
        );

        if (ldap_mod_replace($ldap_connection, $regenerate_dn, $modifications)) {
          // Audit log backup code regeneration
          audit_log('mfa_backup_codes_regenerated', $account_identifier, "Admin regenerated backup codes for user", 'success', $USER_ID);
          render_alert_banner("New backup codes have been generated. The user should be notified to collect them from their Manage MFA page.");
        } else {
          audit_log('mfa_backup_codes_regen_failure', $account_identifier, "Failed to regenerate backup codes", 'failure', $USER_ID);
          render_alert_banner("Failed to regenerate backup codes. Check the logs for more information.", "danger", 15000);
        }
      }
    }
  }
}

?>
