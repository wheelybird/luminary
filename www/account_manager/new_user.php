<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "module_functions.inc.php";

$attribute_map = $LDAP['default_attribute_map'];
if (isset($LDAP['account_additional_attributes'])) { $attribute_map = ldap_complete_attribute_array($attribute_map,$LDAP['account_additional_attributes']); }

if (! array_key_exists($LDAP['account_attribute'], $attribute_map)) {
  $attribute_r = array_merge($attribute_map, array($LDAP['account_attribute'] => array("label" => "Account UID")));
}

if ( isset($_POST['setup_admin_account']) ) {

  $admin_setup = TRUE;

  validate_setup_cookie();
  set_page_access("setup");

  $completed_action="{$SERVER_PATH}log_in";
  $page_title="New administrator account";

  render_header("$ORGANISATION_NAME account manager - setup administrator account", FALSE);

}
else {
  set_page_access("admin");

  $completed_action="{$THIS_MODULE_PATH}/";
  $page_title="New account";
  $admin_setup = FALSE;

  render_header("$ORGANISATION_NAME account manager");
  render_submenu();
}

$invalid_password = FALSE;
$mismatched_passwords = FALSE;
$invalid_username = FALSE;
$weak_password = FALSE;
$invalid_email = FALSE;
$disabled_email_tickbox = TRUE;
$invalid_cn = FALSE;
$invalid_givenname = FALSE;
$invalid_sn = FALSE;
$invalid_account_identifier = FALSE;
$account_attribute = $LDAP['account_attribute'];

$new_account_r = array();

if ($SHOW_POSIX_ATTRIBUTES == TRUE) {

}

foreach ($attribute_map as $attribute => $attr_r) {

  if (isset($_FILES[$attribute]['size']) and $_FILES[$attribute]['size'] > 0) {

    $this_attribute = array();
    $this_attribute['count'] = 1;
    $this_attribute[0] = file_get_contents($_FILES[$attribute]['tmp_name']);
    $$attribute = $this_attribute;
    $new_account_r[$attribute] = $this_attribute;
    unset($new_account_r[$attribute]['count']);

  }

  if (isset($_POST[$attribute])) {

    $this_attribute = array();

    if (is_array($_POST[$attribute]) and count($_POST[$attribute]) > 0) {
      foreach($_POST[$attribute] as $key => $value) {
        if ($value != "") { $this_attribute[$key] = trim($value); }
      }
      if (count($this_attribute) > 0) {
        $this_attribute['count'] = count($this_attribute);
        $$attribute = $this_attribute;
      }
    }
    elseif ($_POST[$attribute] != "") {
      $this_attribute['count'] = 1;
      $this_attribute[0] = trim($_POST[$attribute]);
      $$attribute = $this_attribute;
    }

  }

  if (!isset($$attribute) and isset($attr_r['default'])) {
    $$attribute['count'] = 1;
    $$attribute[0] = $attr_r['default'];
  }

  if (isset($$attribute)) {
    $new_account_r[$attribute] = $$attribute;
    unset($new_account_r[$attribute]['count']);
  }

}

##

if (isset($_GET['account_request'])) {

  $givenname[0]=trim($_GET['first_name']);
  $new_account_r['givenname'] = $givenname[0];

  $sn[0]=trim($_GET['last_name']);
  $new_account_r['sn'] = $sn[0];

  $mail[0]=filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
  if ($mail[0] == "") {
    if (isset($EMAIL_DOMAIN)) {
      $mail[0] = $uid . "@" . $EMAIL_DOMAIN;
      $disabled_email_tickbox = FALSE;
    }
  }
  else {
    $disabled_email_tickbox = FALSE;
  }
  $new_account_r['mail'] = $mail;
  unset($new_account_r['mail']['count']);

}


if (isset($_GET['account_request']) or isset($_POST['create_account'])) {

  // Handle mononym users (only surname) - fixes #213, #171
  $givenname_val = isset($givenname[0]) ? $givenname[0] : '';
  $sn_val = isset($sn[0]) ? $sn[0] : '';

  if (!isset($uid[0])) {
    $uid[0] = generate_username($givenname_val, $sn_val);
    $new_account_r['uid'] = $uid;
    unset($new_account_r['uid']['count']);
  }

  if (!isset($cn[0])) {
    if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE) {
      $cn[0] = $givenname_val . $sn_val;
    }
    else {
      $cn[0] = trim($givenname_val . " " . $sn_val);
    }
    $new_account_r['cn'] = $cn;
    unset($new_account_r['cn']['count']);
  }

}


if (isset($_POST['create_account'])) {

 $password  = $_POST['password'];
 $new_account_r['password'][0] = $password;
 $account_identifier = $new_account_r[$account_attribute][0];
 $this_cn=$cn[0];
 $this_mail=$mail[0];
 // Handle mononym users (fixes #213, #171)
 $this_givenname = isset($givenname[0]) ? $givenname[0] : '';
 $this_sn = isset($sn[0]) ? $sn[0] : '';
 $this_password=$password[0];

 if (!isset($this_cn) or $this_cn == "") { $invalid_cn = TRUE; }
 if ((!isset($account_identifier) or $account_identifier == "") and $invalid_cn != TRUE) { $invalid_account_identifier = TRUE; }
 if (!isset($this_givenname) or $this_givenname == "") { $invalid_givenname = TRUE; }
 if (!isset($this_sn) or $this_sn == "") { $invalid_sn = TRUE; }
 if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $weak_password = TRUE; }
 if (isset($this_mail) and !is_valid_email($this_mail)) { $invalid_email = TRUE; }
 if (preg_match("/\"|'/",$password)) { $invalid_password = TRUE; }
 if ($password != $_POST['password_match']) { $mismatched_passwords = TRUE; }
 if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE and !preg_match("/$USERNAME_REGEX/u",$account_identifier)) { $invalid_account_identifier = TRUE; }
 if (isset($_POST['send_email']) and isset($mail) and $EMAIL_SENDING_ENABLED == TRUE) { $send_user_email = TRUE; }

 if (     isset($this_givenname)
      and isset($this_sn)
      and isset($this_password)
      and !$mismatched_passwords
      and !$weak_password
      and !$invalid_password
      and !$invalid_account_identifier
      and !$invalid_cn
      and !$invalid_email) {

  $ldap_connection = open_ldap_connection();
  $new_account = ldap_new_account($ldap_connection, $new_account_r);

  if ($new_account) {

    // Check if MFA is enabled and if user will be in an MFA-required group
    if ($MFA_ENABLED == TRUE && !empty($MFA_REQUIRED_GROUPS)) {
      // Get the groups this user will be added to
      $user_groups = array();
      if (isset($DEFAULT_USER_GROUP)) {
        $user_groups[] = $DEFAULT_USER_GROUP;
      }

      // Check if any of the user's groups require MFA
      $requires_mfa = false;
      foreach ($user_groups as $group) {
        if (in_array($group, $MFA_REQUIRED_GROUPS)) {
          $requires_mfa = true;
          break;
        }
      }

      // If MFA is required and schema is available, set status to pending
      if ($requires_mfa && $MFA_SCHEMA_OK) {
        $user_dn = "{$LDAP['account_attribute']}={$account_identifier},{$LDAP['user_dn']}";

        // Add TOTP objectClass if not present
        $oc_mod = array('objectClass' => $TOTP_ATTRS['objectclass']);
        @ldap_mod_add($ldap_connection, $user_dn, $oc_mod);

        // Set initial MFA status to pending with enrolled date
        $mfa_attributes = array(
          $TOTP_ATTRS['status'] => 'pending',
          $TOTP_ATTRS['enrolled_date'] => gmdate('YmdHis') . 'Z'
        );
        ldap_mod_replace($ldap_connection, $user_dn, $mfa_attributes);
      }
      elseif ($requires_mfa && !$MFA_SCHEMA_OK) {
        error_log("Cannot set MFA pending status for new user {$account_identifier}: TOTP schema not available");
      }
    }

    $creation_message = "The account was created.";

    if (isset($send_user_email) and $send_user_email == TRUE) {

      include_once "mail_functions.inc.php";

      // Handle mononym users for email (fixes #213, #171)
      $full_name = trim($this_givenname . " " . $this_sn);

      $mail_body = parse_mail_text($new_account_mail_body, $password, $account_identifier, $this_givenname, $this_sn);
      $mail_subject = parse_mail_text($new_account_mail_subject, $password, $account_identifier, $this_givenname, $this_sn);

      $sent_email = send_email($this_mail, $full_name, $mail_subject, $mail_body);
      $creation_message = "The account was created";
      if ($sent_email) {
        $creation_message .= " and an email sent to $this_mail.";
      }
      else {
        $creation_message .= " but unfortunately the email wasn't sent.<br>More information will be available in the logs.";
      }
    }

    if ($admin_setup == TRUE) {
      $member_add = ldap_add_member_to_group($ldap_connection, $LDAP['admins_group'], $account_identifier);
      if (!$member_add) { ?>
       <div class="alert alert-warning">
        <p class="text-center"><?php print $creation_message; ?> Unfortunately adding it to the admin group failed.</p>
       </div>
       <?php
      }
     #Tidy up empty uniquemember entries left over from the setup wizard
     $USER_ID="tmp_admin";
     ldap_delete_member_from_group($ldap_connection, $LDAP['admins_group'], "");
     if (isset($DEFAULT_USER_GROUP)) { ldap_delete_member_from_group($ldap_connection, $DEFAULT_USER_GROUP, ""); }
    }

   ?>
   <div class="alert alert-success">
   <p class="text-center"><?php print $creation_message; ?></p>
   </div>
   <form action='<?php print $completed_action; ?>'>
    <p align="center">
     <input type='submit' class="btn btn-success" value='Finished'>
    </p>
   </form>
   <?php
   render_footer();
   exit(0);
  }
  else {
  ?>
    <div class="alert alert-warning">
     <p class="text-center">Failed to create the account:</p>
     <pre>
     <?php
       print ldap_error($ldap_connection) . "\n";
       ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
       print $detailed_err;
     ?>
     </pre>
    </div>
    <?php

   render_footer();
   exit(0);

  }

 }

}

$errors="";
if ($invalid_cn) { $errors.="<li>The Common Name is required</li>\n"; }
if ($invalid_givenname) { $errors.="<li>First Name is required</li>\n"; }
if ($invalid_sn) { $errors.="<li>Last Name is required</li>\n"; }
if ($invalid_account_identifier) {  $errors.="<li>The account identifier (" . $attribute_map[$account_attribute]['label'] . ") is invalid.</li>\n"; }
if ($weak_password) { $errors.="<li>The password is too weak</li>\n"; }
if ($invalid_password) { $errors.="<li>The password contained invalid characters</li>\n"; }
if ($invalid_email) { $errors.="<li>The email address is invalid</li>\n"; }
if ($mismatched_passwords) { $errors.="<li>The passwords are mismatched</li>\n"; }
if ($invalid_username) { $errors.="<li>The username is invalid</li>\n"; }

if ($errors != "") { ?>
<div class="alert alert-warning">
 <p class="text-align: center">
 There were issues creating the account:
 <ul>
 <?php print $errors; ?>
 </ul>
 </p>
</div>
<?php
}

render_js_username_check();
render_js_username_generator('givenname','sn','uid','uid_div');
render_js_cn_generator('givenname','sn','cn','cn_div');
render_js_email_generator('uid','mail');
render_js_homedir_generator('uid','homedirectory');

$tabindex=1;

?>
<script src="<?php print $SERVER_PATH; ?>js/password-utils.js"></script>
<script>

 // Initialize password strength meter
 document.addEventListener('DOMContentLoaded', function() {
   initPasswordStrength('password');
 });

 function check_passwords_match() {
   const password = document.getElementById('password');
   const confirm = document.getElementById('confirm');

   if (password.value != confirm.value) {
       password.classList.add("is-invalid");
       confirm.classList.add("is-invalid");
   }
   else {
    password.classList.remove("is-invalid");
    confirm.classList.remove("is-invalid");
   }
  }

 function random_password() {
  generatePassword(4,'-','password','confirm');
  check_email_validity(document.getElementById('mail').value);
 }

 function back_to_hidden(passwordField,confirmField) {

  var passwordField = document.getElementById(passwordField).type = 'password';
  var confirmField = document.getElementById(confirmField).type = 'password';

 }


</script>
<script>

 function check_email_validity(mail) {

  var check_regex = <?php print $JS_EMAIL_REGEX; ?>

  if (! check_regex.test(mail) ) {
   document.getElementById("mail_div").classList.add("is-invalid");
   <?php if ($EMAIL_SENDING_ENABLED == TRUE) { ?>document.getElementById("send_email_checkbox").disabled = true;<?php } ?>
  }
  else {
   document.getElementById("mail_div").classList.remove("is-invalid");
   <?php if ($EMAIL_SENDING_ENABLED == TRUE) { ?>document.getElementById("send_email_checkbox").disabled = false;<?php } ?>
  }

 }

</script>

<?php render_dynamic_field_js(); ?>

<div class="container">
 <div class="col-sm-8 offset-md-2">

  <div class="card">
   <div class="card-header text-center"><?php print $page_title; ?></div>
   <div class="card-body text-center">

    <form class="form-horizontal" action="" enctype="multipart/form-data" method="post">

     <?php if ($admin_setup == TRUE) { ?><input type="hidden" name="setup_admin_account" value="true"><?php } ?>
     <input type="hidden" name="create_account">
     <input type="hidden" id="pass_score" value="0" name="pass_score">

     <?php
       foreach ($attribute_map as $attribute => $attr_r) {
         $label = $attr_r['label'];
         if (isset($attr_r['onkeyup'])) { $onkeyup = $attr_r['onkeyup']; } else { $onkeyup = ""; }
         if ($attribute == $LDAP['account_attribute']) { $label = "<strong>$label</strong><sup>&ast;</sup>"; }
         if (isset($attr_r['required']) and $attr_r['required'] == TRUE) { $label = "<strong>$label</strong><sup>&ast;</sup>"; }
         if (isset($$attribute)) { $these_values=$$attribute; } else { $these_values = array(); }
         if (isset($attr_r['inputtype'])) { $inputtype = $attr_r['inputtype']; } else { $inputtype = ""; }
         render_attribute_fields($attribute,$label,$these_values,"",$onkeyup,$inputtype,$tabindex);
         $tabindex++;
       }
     ?>

     <div class="row mb-3" id="password_div">
      <label for="password" class="col-sm-3 col-form-label">Password</label>
      <div class="col-sm-6">
       <input tabindex="<?php print $tabindex+1; ?>" type="text" class="form-control" id="password" name="password" onkeyup="back_to_hidden('password','confirm');">
      </div>
      <div class="col-sm-1">
       <input tabindex="<?php print $tabindex+3; ?>" type="button" class="btn btn-primary btn-sm" id="password_generator" onclick="random_password();" value="Generate password">
      </div>
     </div>

     <div class="row mb-3" id="confirm_div">
      <label for="confirm" class="col-sm-3 col-form-label">Confirm</label>
      <div class="col-sm-6">
       <input tabindex="<?php print $tabindex+2; ?>" type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
      </div>
     </div>

<?php  if ($EMAIL_SENDING_ENABLED == TRUE and $admin_setup != TRUE) { ?>
      <div class="row mb-3" id="send_email_div">
       <label for="send_email" class="col-sm-3 col-form-label"> </label>
       <div class="col-sm-6">
        <input tabindex="<?php print $tabindex+4; ?>" type="checkbox" class="form-check-input" id="send_email_checkbox" name="send_email" <?php if ($disabled_email_tickbox == TRUE) { print "disabled"; } ?>>  Email these credentials to the user?
       </div>
      </div>
<?php } ?>

     <div class="row mb-3">
       <button tabindex="<?php print $tabindex+5; ?>" type="submit" class="btn btn-warning">Create account</button>
     </div>

    </form>

    <div class="progress">
     <div id="StrengthProgressBar" class="progress-bar"></div>
    </div>

    <div><sup>&ast;</sup>The account identifier</div>

   </div>
  </div>

 </div>
</div>
<?php



render_footer();

?>
