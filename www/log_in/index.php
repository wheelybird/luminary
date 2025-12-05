<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";

if (isset($_GET["unauthorised"])) { $display_unauth = TRUE; }
if (isset($_GET["session_timeout"])) { $display_logged_out = TRUE; }
if (isset($_GET["redirect_to"])) { $redirect_to = $_GET["redirect_to"]; }

if (isset($_GET['logged_out'])) {
?>
<div class="container">
 <div class="alert alert-warning">
  <p class="text-center">You've been automatically logged out because you've been inactive for over
  <?php print $SESSION_TIMEOUT; ?> minutes. Click on the 'Log in' link to get back into the system.</p>
 </div>
</div>
<?php
}


if (isset($_POST["user_id"]) and isset($_POST["password"])) {

 $ldap_connection = open_ldap_connection();
 $account_id = ldap_auth_username($ldap_connection,$_POST["user_id"],$_POST["password"]);
 $is_admin = ldap_is_group_member($ldap_connection,$LDAP['admins_group'],$account_id);

 if ($account_id != FALSE) {

  // Check MFA schema status dynamically if MFA is enabled
  if ($MFA_FEATURE_ENABLED == TRUE) {
    $MFA_SCHEMA_OK = totp_check_schema($ldap_connection);
    $MFA_FULLY_OPERATIONAL = $MFA_SCHEMA_OK;
  }

  // Check MFA status if MFA is fully operational
  $mfa_redirect_needed = false;
  if ($MFA_FULLY_OPERATIONAL == TRUE) {

   // Get user's MFA status
   $status_attr = $TOTP_ATTRS['status'];
   $enrolled_attr = $TOTP_ATTRS['enrolled_date'];
   $status_attr_lower = strtolower($status_attr);
   $enrolled_attr_lower = strtolower($enrolled_attr);

   $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
     "({$LDAP['account_attribute']}=" . ldap_escape($account_id, "", LDAP_ESCAPE_FILTER) . ")",
     array($status_attr, $enrolled_attr, 'memberOf'));

   if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
     $totp_status = isset($user_entry[0][$status_attr_lower][0]) ? $user_entry[0][$status_attr_lower][0] : 'none';
     $totp_enrolled_date = isset($user_entry[0][$enrolled_attr_lower][0]) ? $user_entry[0][$enrolled_attr_lower][0] : null;

     // Check if user is in MFA-required group
     $mfa_result = totp_user_requires_mfa($ldap_connection, $account_id, $MFA_REQUIRED_GROUPS);
     $user_requires_mfa = $mfa_result['required'];

     if ($user_requires_mfa) {
      // Use group-specific grace period if available, otherwise use global setting
      $grace_period = $mfa_result['grace_period'] !== null ? $mfa_result['grace_period'] : $MFA_GRACE_PERIOD_DAYS;

      // If MFA is not active, check grace period
      if ($totp_status !== 'active') {

       if ($totp_status == 'pending' && $totp_enrolled_date) {
        // User has pending status - check if grace period has expired
        $grace_period_remaining = totp_grace_period_remaining($totp_enrolled_date, $grace_period);

        // Only redirect if grace period has actually expired
        if ($grace_period_remaining <= 0) {
         $mfa_redirect_needed = true;
        }
       }
       elseif ($totp_status == 'none' || $totp_status == 'disabled') {
        // User has no MFA configured and no grace period - redirect immediately
        $mfa_redirect_needed = true;
       }
      }
     }
    }
   }
  }

  // Check account expiration if account lifecycle is enabled
  $account_expiry_redirect_needed = false;
  if ($LIFECYCLE_ENABLED == TRUE && $ACCOUNT_EXPIRY_ENABLED == TRUE) {
    include_once "account_lifecycle_functions.inc.php";

    // We need to get the user DN for account expiry check
    $ldap_connection_lifecycle = open_ldap_connection();
    $user_search_lifecycle = ldap_search($ldap_connection_lifecycle, $LDAP['user_dn'],
      "({$LDAP['account_attribute']}=" . ldap_escape($account_id, "", LDAP_ESCAPE_FILTER) . ")",
      array('dn'));

    if ($user_search_lifecycle) {
      $user_entry_lifecycle = ldap_get_entries($ldap_connection_lifecycle, $user_search_lifecycle);
      if ($user_entry_lifecycle['count'] > 0) {
        $user_dn = $user_entry_lifecycle[0]['dn'];

        // Check account expiration
        $account_days_remaining = null;
        $account_expired = account_lifecycle_is_expired($ldap_connection_lifecycle, $user_dn, $account_days_remaining);
        $account_should_warn = account_lifecycle_should_warn($ldap_connection_lifecycle, $user_dn, $account_days_remaining);

        // Store account expiry info in temporary variables for later session storage
        $temp_account_expired = $account_expired;
        $temp_account_days_remaining = $account_days_remaining;
        $temp_account_should_warn = $account_should_warn;

        if ($account_expired) {
          $account_expiry_redirect_needed = true;
        }
      }
    }
    ldap_close($ldap_connection_lifecycle);
  }

  // Check password expiry if password policy and ppolicy are enabled
  $password_expiry_redirect_needed = false;
  if ($PASSWORD_POLICY_ENABLED == TRUE && $PPOLICY_ENABLED == TRUE && $PASSWORD_EXPIRY_DAYS > 0) {
    include_once "password_policy_functions.inc.php";

    // We need to get the user DN for password expiry check - reopen connection
    $ldap_connection_expiry = open_ldap_connection();
    $user_search_expiry = ldap_search($ldap_connection_expiry, $LDAP['user_dn'],
      "({$LDAP['account_attribute']}=" . ldap_escape($account_id, "", LDAP_ESCAPE_FILTER) . ")",
      array('dn'));

    if ($user_search_expiry) {
      $user_entry_expiry = ldap_get_entries($ldap_connection_expiry, $user_search_expiry);
      if ($user_entry_expiry['count'] > 0) {
        $user_dn = $user_entry_expiry[0]['dn'];

        // Check password expiry
        $days_remaining = null;
        $password_expired = password_policy_is_expired($ldap_connection_expiry, $user_dn, $days_remaining);
        $should_warn = password_policy_should_warn($ldap_connection_expiry, $user_dn, $days_remaining);

        // Store expiry info in temporary variables for later session storage
        // (Session will be set by set_passkey_cookie() later)
        $temp_password_expired = $password_expired;
        $temp_password_days_remaining = $days_remaining;
        $temp_password_should_warn = $should_warn;

        if ($password_expired) {
          $password_expiry_redirect_needed = true;
        }
      }
    }
    ldap_close($ldap_connection_expiry);
  }

  ldap_close($ldap_connection);

  // Check if user is in MFA-required group and needs to validate TOTP during login
  $totp_validation_needed = false;
  if ($MFA_FULLY_OPERATIONAL == TRUE) {
    // If user is in an MFA-required group and has active MFA, require TOTP validation for login
    if (isset($user_requires_mfa) && $user_requires_mfa && isset($totp_status) && $totp_status === 'active') {
      $totp_validation_needed = true;
    }
    // If user is in MFA-required group but doesn't have active MFA, handle grace period
    elseif (isset($user_requires_mfa) && $user_requires_mfa && $totp_status !== 'active') {
      // Grace period will be handled by $mfa_redirect_needed which was already set above
    }
  }

  // If TOTP validation is needed for login, store temp session and redirect to TOTP page
  if ($totp_validation_needed) {
    // Store temporary authentication state (password validated, awaiting TOTP)
    $temp_auth_key = bin2hex(random_bytes(32));
    $temp_data = json_encode(array(
      'user_id' => $account_id,
      'is_admin' => $is_admin,
      'timestamp' => time(),
      'redirect_to' => isset($_POST["redirect_to"]) ? $_POST["redirect_to"] : null
    ));
    $temp_filename = preg_replace('/[^a-zA-Z0-9]/','_', "mfa_pending_" . $temp_auth_key);
    @ file_put_contents("/tmp/$temp_filename", $temp_data);

    // Set cookie for temp auth (expires in 5 minutes)
    $temp_cookie_opts = $DEFAULT_COOKIE_OPTIONS;
    $temp_cookie_opts['expires'] = time() + 300; // 5 minutes
    setcookie('mfa_temp_auth', $temp_auth_key, $temp_cookie_opts);

    header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in/verify_totp.php\n\n");
  }
  else {
    // No TOTP validation needed, proceed with normal login
    set_passkey_cookie($account_id,$is_admin);

    // Store account expiration information in session (if checked)
    if (isset($temp_account_expired)) {
      $_SESSION['account_expired'] = $temp_account_expired;
      $_SESSION['account_days_remaining'] = $temp_account_days_remaining;
      $_SESSION['account_should_warn'] = $temp_account_should_warn;
      $_SESSION['account_check_time'] = time();
    }

    // Store password expiry information in session (if checked)
    if (isset($temp_password_expired)) {
      $_SESSION['password_expired'] = $temp_password_expired;
      $_SESSION['password_days_remaining'] = $temp_password_days_remaining;
      $_SESSION['password_should_warn'] = $temp_password_should_warn;
      $_SESSION['password_check_time'] = time();
    }

    // Audit log successful login
    audit_log('login_success', $account_id, $is_admin ? 'admin' : 'user', 'success', $account_id);

    // If account has expired, redirect to account-expired page (highest priority)
    if ($account_expiry_redirect_needed) {
     header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in/account_expired.php\n\n");
    }
    // If password has expired, redirect to change password page (second priority)
    elseif ($password_expiry_redirect_needed) {
     header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}change_password?password_expired\n\n");
    }
    // If MFA setup is required, redirect to Manage MFA page
    elseif ($mfa_redirect_needed) {
     header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}manage_mfa?mfa_required\n\n");
    }
    elseif (isset($_POST["redirect_to"])) {
     $redirect_url = base64_decode($_POST['redirect_to']);
     // If admin is being redirected to home page, send them to account_manager instead
     if ($is_admin && (strpos($redirect_url, '/home') !== false || strpos($redirect_url, 'home/') !== false)) {
      header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}account_manager?logged_in\n\n");
     } else {
      header("Location: //{$_SERVER['HTTP_HOST']}" . $redirect_url . "\n\n");
     }
    }
    else {
     $default_module = $is_admin ? "account_manager" : "home";
     header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}$default_module?logged_in\n\n");
    }
  }
 }
 else {
  // Audit log failed login attempt
  audit_log('login_failure', $_POST["user_id"] ?? 'unknown', 'Invalid credentials', 'failure', $_POST["user_id"] ?? 'unknown');

  ldap_close($ldap_connection);
  header("Location: //{$_SERVER['HTTP_HOST']}{$THIS_MODULE_PATH}/index.php?invalid\n\n");
 }

}
else {

 render_header("$ORGANISATION_NAME account manager - log in");

 ?>
<div class="container">
 <div class="row justify-content-center">
  <div class="col-md-8">

   <div class="card">
   <div class="card-header text-center">Log in</div>
   <div class="card-body text-center">

   <?php if (isset($display_unauth)) { ?>
   <div class="alert alert-warning">
    Please log in to continue
   </div>
   <?php } ?>

   <?php if (isset($display_logged_out)) { ?>
   <div class="alert alert-warning">
    You were logged out because your session expired. Log in again to continue.
   </div>
   <?php } ?>

   <?php if (isset($_GET["invalid"])) { ?>
   <div class="alert alert-warning">
    The username and/or password are unrecognised.
   </div>
   <?php } ?>

   <form class="form-horizontal" action='' method='post'>
    <?php if (isset($redirect_to) and ($redirect_to != "")) { ?><input type="hidden" name="redirect_to" value="<?php print htmlspecialchars($redirect_to); ?>"><?php } ?>

    <div class="row mb-3">
     <label for="username" class="col-sm-4 col-form-label text-end"><?php print $SITE_LOGIN_FIELD_LABEL; ?></label>
     <div class="col-sm-6">
      <input type="text" class="form-control" id="user_id" name="user_id">
     </div>
    </div>

    <div class="row mb-3">
     <label for="password" class="col-sm-4 col-form-label text-end">Password</label>
     <div class="col-sm-6">
      <input type="password" class="form-control" id="confirm" name="password">
     </div>
    </div>

    <?php if ($PASSWORD_RESET_ENABLED == TRUE && $EMAIL_SENDING_ENABLED == TRUE) { ?>
    <div class="text-center mb-3">
      <a href="<?php echo url('/password_reset/request.php'); ?>">Forgot password?</a>
    </div>
    <?php } ?>

    <div class="text-center mb-3">
     <button type="submit" class="btn btn-secondary">Log in</button>
    </div>

   </form>
   </div>
  </div>
 </div>
</div>
<?php
}
render_footer();
?>
