<?php

/**
 * User Details Handler
 * Processes account detail updates (attributes, password, email)
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

### Update values

if (isset($_POST['update_account'])) {

  // Handle mononym users (only surname) - fixes #213, #171
  $givenname_val = isset($givenname[0]) ? $givenname[0] : '';
  $sn_val = isset($sn[0]) ? $sn[0] : '';

  if (!isset($uid[0])) {
    $uid[0] = generate_username($givenname_val, $sn_val);
    $to_update['uid'] = $uid;
    unset($to_update['uid']['count']);
  }

  if (!isset($cn[0])) {
    if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE) {
      $cn[0] = $givenname_val . $sn_val;
    }
    else {
      $cn[0] = trim($givenname_val . " " . $sn_val);
    }
    $to_update['cn'] = $cn;
    unset($to_update['cn']['count']);
  }

  if (isset($_POST['password']) and $_POST['password'] != "") {

    $password = $_POST['password'];

    // Use password policy strength check if enabled, otherwise fall back to client-side score
    if (!$PASSWORD_POLICY_ENABLED && (!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $weak_password = TRUE; }
    if (preg_match("/\"|'/",$password)) { $invalid_password = TRUE; }
    if ($_POST['password'] != $_POST['password_match']) { $mismatched_passwords = TRUE; }
    if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE and !preg_match("/$USERNAME_REGEX/u",$account_identifier)) { $invalid_username = TRUE; }

    // Password policy validation
    $password_policy_errors = array();
    $password_fails_policy = false;
    if (!password_policy_validate($password, $password_policy_errors)) {
      $password_fails_policy = true;
    }
    $password_strength_score = 0;
    if (!password_policy_check_strength($password, $password_strength_score)) {
      $password_fails_policy = true;
      if (empty($password_policy_errors)) {
        $password_policy_errors[] = "Password strength is too weak";
      }
    }

    // Check password history
    $password_in_history = FALSE;
    if (!$password_fails_policy && password_policy_check_history($ldap_connection, $dn, $password)) {
      $password_in_history = TRUE;
      $password_policy_errors[] = "Password was used recently and cannot be reused";
    }

    if ( !$mismatched_passwords
       and !$weak_password
       and !$invalid_password
       and !$password_fails_policy
       and !$password_in_history
                             ) {
     error_log("$log_prefix Password change requested for {$dn}");
     error_log("$log_prefix PASSWORD_POLICY_ENABLED=" . var_export($PASSWORD_POLICY_ENABLED, true));
     // Try to use Password Modify Extended Operation if ppolicy is available
     // This allows ppolicy overlay to track password history automatically
     if ($PASSWORD_POLICY_ENABLED && password_policy_change_password($ldap_connection, $dn, $password)) {
       $password_was_changed = true;
       $password_changed_via_exop = true;
       error_log("$log_prefix Password changed via extended operation");
     } else {
       // Fall back to traditional method (pre-hash the password)
       error_log("$log_prefix Falling back to traditional password change");
       $password_hash = ldap_hashed_password($password);
       $to_update['userpassword'][0] = $password_hash;
       $password_was_changed = true;
       $new_password_hash = $password_hash;
     }
    }
  }

  if (array_key_exists($LDAP['account_attribute'], $to_update)) {
    $account_attribute = $LDAP['account_attribute'];
    $new_account_identifier = $to_update[$account_attribute][0];
    $new_rdn = "{$account_attribute}={$new_account_identifier}";
    $renamed_entry = ldap_rename($ldap_connection, $dn, $new_rdn, $LDAP['user_dn'], true);
    if ($renamed_entry) {
      $dn = "{$new_rdn},{$LDAP['user_dn']}";
      $account_identifier = $new_account_identifier;
    }
    else {
      ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
      error_log("$log_prefix Failed to rename the DN for {$account_identifier}: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    }
  }

  $existing_objectclasses = $user[0]['objectclass'];
  unset($existing_objectclasses['count']);
  if ($existing_objectclasses != $LDAP['account_objectclasses']) { $to_update['objectclass'] = $LDAP['account_objectclasses']; }

  $updated_account = @ ldap_mod_replace($ldap_connection, $dn, $to_update);

  if (!$updated_account) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to modify account details for {$account_identifier}: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
  }

  $sent_email_message="";
  if ($updated_account and isset($mail) and $can_send_email == TRUE and isset($_POST['send_email'])) {

      include_once "mail_functions.inc.php";

      // Handle mononym users for email (fixes #213, #171)
      $givenname_for_mail = isset($givenname[0]) ? $givenname[0] : '';
      $sn_for_mail = isset($sn[0]) ? $sn[0] : '';
      $full_name = trim($givenname_for_mail . " " . $sn_for_mail);

      $mail_body = parse_mail_text($new_account_mail_body, $password, $account_identifier, $givenname_for_mail, $sn_for_mail);
      $mail_subject = parse_mail_text($new_account_mail_subject, $password, $account_identifier, $givenname_for_mail, $sn_for_mail);

      $sent_email = send_email($mail[0], $full_name, $mail_subject, $mail_body);
      if ($sent_email) {
        $sent_email_message .= "  An email sent to {$mail[0]}.";
      }
      else {
        $sent_email_message .= "  Unfortunately the email wasn't sent; check the logs for more information.";
      }
    }

  if ($updated_account) {
    // Update password policy tracking if password was changed via traditional method
    // (If changed via exop, ppolicy overlay handles this automatically)
    if (isset($password_was_changed) && $password_was_changed && isset($new_password_hash) && !isset($password_changed_via_exop)) {
      password_policy_set_changed_time($ldap_connection, $dn);
      password_policy_add_to_history($ldap_connection, $dn, $new_password_hash);
    }

    // Send password reset email notification if password was changed
    if (isset($password_was_changed) && $password_was_changed && isset($mail[0]) && !empty($mail[0]) && $can_send_email == TRUE) {
      include_once "mail_functions.inc.php";

      // Handle mononym users for email (fixes #213, #171)
      $givenname_for_mail = isset($givenname[0]) ? $givenname[0] : '';
      $sn_for_mail = isset($sn[0]) ? $sn[0] : '';

      $reset_mail_body = parse_mail_text(
        $reset_password_mail_body,
        '', // No password in reset email
        $account_identifier,
        $givenname_for_mail,
        $sn_for_mail
      );
      $reset_mail_subject = parse_mail_text(
        $reset_password_mail_subject,
        '',
        $account_identifier,
        $givenname_for_mail,
        $sn_for_mail
      );

      $full_name = trim($givenname_for_mail . " " . $sn_for_mail);
      send_email($mail[0], $full_name, $reset_mail_subject, $reset_mail_body);
    }

    // Audit log user update
    $update_fields = array_keys($to_update);
    $update_details = "Updated fields: " . implode(', ', $update_fields);
    audit_log('user_updated', $account_identifier, $update_details, 'success', $USER_ID);
    render_alert_banner("The account has been updated.  $sent_email_message");
  }
  else {
    // Audit log failed update
    $error_msg = ldap_error($ldap_connection);
    audit_log('user_update_failure', $account_identifier, "Failed to update user: {$error_msg}", 'failure', $USER_ID);
    render_alert_banner("There was a problem updating the account.  Check the logs for more information.","danger",15000);
  }
}

?>
