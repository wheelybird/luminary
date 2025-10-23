<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include "web_functions.inc.php";
include "ldap_functions.inc.php";
include "totp_functions.inc.php";

if (isset($_GET["unauthorised"])) { $display_unauth = TRUE; }
if (isset($_GET["session_timeout"])) { $display_logged_out = TRUE; }
if (isset($_GET["redirect_to"])) { $redirect_to = $_GET["redirect_to"]; }

if (isset($_GET['logged_out'])) {
?>
<div class="alert alert-warning">
<p class="text-center">You've been automatically logged out because you've been inactive for over
<?php print $SESSION_TIMEOUT; ?> minutes. Click on the 'Log in' link to get back into the system.</p>
</div>
<?php
}


if (isset($_POST["user_id"]) and isset($_POST["password"])) {

 $ldap_connection = open_ldap_connection();
 $account_id = ldap_auth_username($ldap_connection,$_POST["user_id"],$_POST["password"]);
 $is_admin = ldap_is_group_member($ldap_connection,$LDAP['admins_group'],$account_id);

 if ($account_id != FALSE) {

  // Check MFA schema status dynamically if MFA is enabled
  if ($MFA_ENABLED == TRUE) {
    $MFA_SCHEMA_OK = totp_check_schema($ldap_connection);
    $MFA_FULLY_OPERATIONAL = $MFA_SCHEMA_OK;
  }

  // Check MFA status if MFA is fully operational
  $mfa_redirect_needed = false;
  if ($MFA_FULLY_OPERATIONAL == TRUE && !empty($MFA_REQUIRED_GROUPS)) {

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
     $user_requires_mfa = totp_user_requires_mfa($ldap_connection, $account_id, $MFA_REQUIRED_GROUPS);

     if ($user_requires_mfa) {
      // If MFA is not active, check grace period
      if ($totp_status !== 'active') {

       if ($totp_status == 'pending' && $totp_enrolled_date) {
        // User has pending status - check if grace period has expired
        $grace_period_remaining = totp_grace_period_remaining($totp_enrolled_date, $MFA_GRACE_PERIOD_DAYS);

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

  ldap_close($ldap_connection);

  set_passkey_cookie($account_id,$is_admin);

  // If MFA setup is required, redirect to Manage MFA page
  if ($mfa_redirect_needed) {
   header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}manage_mfa?mfa_required\n\n");
  }
  elseif (isset($_POST["redirect_to"])) {
   header("Location: //{$_SERVER['HTTP_HOST']}" . base64_decode($_POST['redirect_to']) . "\n\n");
  }
  else {
   $default_module = "home";
   header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}$default_module?logged_in\n\n");
  }
 }
 else {
  ldap_close($ldap_connection);
  header("Location: //{$_SERVER['HTTP_HOST']}{$THIS_MODULE_PATH}/index.php?invalid\n\n");
 }

}
else {

 render_header("$ORGANISATION_NAME account manager - log in");

 ?>
<div class="container">
 <div class="col-sm-8 col-sm-offset-2">

  <div class="panel panel-default">
   <div class="panel-heading text-center">Log in</div>
   <div class="panel-body text-center">

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

    <div class="form-group">
     <label for="username" class="col-sm-4 control-label"><?php print $SITE_LOGIN_FIELD_LABEL; ?></label>
     <div class="col-sm-6">
      <input type="text" class="form-control" id="user_id" name="user_id">
     </div>
    </div>

    <div class="form-group">
     <label for="password" class="col-sm-4 control-label">Password</label>
     <div class="col-sm-6">
      <input type="password" class="form-control" id="confirm" name="password">
     </div>
    </div>

    <div class="form-group">
     <button type="submit" class="btn btn-default">Log in</button>
    </div>

   </form>
  </div>
 </div>
</div>
<?php
}
render_footer();
?>
