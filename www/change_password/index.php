<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "password_policy_functions.inc.php";

set_page_access("user");

// Check if this is a forced password change due to expiry
$password_expired_forced = isset($_GET['password_expired']);

// Get password expiry information if password policy is enabled
$password_age_days = null;
$password_expires_in_days = null;
$password_changed_time = null;
$password_changed_formatted = null;

if ($PASSWORD_POLICY_ENABLED && $PPOLICY_ENABLED && $PASSWORD_EXPIRY_DAYS > 0) {
  $ldap_connection = open_ldap_connection();

  // Get user DN
  $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
    "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
    array('dn'));

  if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
      $user_dn = $user_entry[0]['dn'];

      // Get password changed time
      $password_changed_time = password_policy_get_changed_time($ldap_connection, $user_dn);

      if ($password_changed_time) {
        // Parse LDAP timestamp (format: YYYYMMDDHHMMSSZ)
        $timestamp = strtotime($password_changed_time);
        $password_changed_formatted = date('F j, Y', $timestamp);

        // Calculate age
        $age_seconds = time() - $timestamp;
        $password_age_days = floor($age_seconds / 86400);

        // Calculate days until expiry
        $password_expires_in_days = $PASSWORD_EXPIRY_DAYS - $password_age_days;
      }
    }
  }

  ldap_close($ldap_connection);
}

if (isset($_POST['change_password'])) {

 if (!$_POST['password']) { $not_strong_enough = 1; }
 if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $not_strong_enough = 1; }
 if (preg_match("/\"|'/",$_POST['password'])) { $invalid_chars = 1; }
 if ($_POST['password'] != $_POST['password_match']) { $mismatched = 1; }

 // ppolicy requires current password
 if ($PPOLICY_ENABLED && !isset($_POST['current_password'])) {
   $missing_current_password = 1;
 }

 if (!isset($mismatched) and !isset($not_strong_enough) and !isset($invalid_chars) and !isset($missing_current_password)) {

  $password_changed = false;
  $change_error = null;

  // Try ppolicy-aware password change if enabled
  if ($PPOLICY_ENABLED) {
    $password_changed = password_policy_self_service_change($USER_ID, $_POST['current_password'], $_POST['password'], $change_error);
    if (!$password_changed && $change_error) {
      // Check for ppolicy-specific errors
      if (stripos($change_error, 'history') !== false) {
        $password_in_history = 1;
      } elseif (stripos($change_error, 'current password') !== false || stripos($change_error, 'Invalid credentials') !== false) {
        $invalid_current_password = 1;
      } else {
        $ppolicy_error = $change_error;
      }
    }
  }

  // Fall back to traditional method if ppolicy not enabled or failed
  if (!$password_changed && !isset($password_in_history) && !isset($invalid_current_password) && !isset($ppolicy_error)) {
    $ldap_connection = open_ldap_connection();
    $password_changed = ldap_change_password($ldap_connection,$USER_ID,$_POST['password']);
    if (!$password_changed) {
      die("change_ldap_password() failed.");
    }
  }

  if ($password_changed) {
    // Send password reset email notification
    if ($EMAIL_SENDING_ENABLED) {
      $ldap_connection = open_ldap_connection();

      // Get user details for email
      $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
        "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
        array('mail', 'givenname', 'sn'));

      if ($user_search) {
        $user_entry = ldap_get_entries($ldap_connection, $user_search);
        if ($user_entry['count'] > 0 && isset($user_entry[0]['mail'][0])) {
          include_once "mail_functions.inc.php";

          $user_mail = $user_entry[0]['mail'][0];
          $user_givenname = isset($user_entry[0]['givenname'][0]) ? $user_entry[0]['givenname'][0] : '';
          $user_sn = isset($user_entry[0]['sn'][0]) ? $user_entry[0]['sn'][0] : '';
          $full_name = trim($user_givenname . " " . $user_sn);

          $reset_mail_body = parse_mail_text(
            $reset_password_mail_body,
            '', // No password in reset email
            $USER_ID,
            $user_givenname,
            $user_sn
          );
          $reset_mail_subject = parse_mail_text(
            $reset_password_mail_subject,
            '',
            $USER_ID,
            $user_givenname,
            $user_sn
          );

          send_email($user_mail, $full_name, $reset_mail_subject, $reset_mail_body);
        }
      }

      ldap_close($ldap_connection);
    }

    render_header("$ORGANISATION_NAME account manager - password changed");
  ?>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card border-success">
          <div class="card-header text-center">Success</div>
          <div class="card-body">
            Your password has been updated
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
  render_footer();
  exit(0);
  }
 }

}

render_header("Change your $ORGANISATION_NAME password");

?>
<div class="container">
<?php

if (isset($not_strong_enough)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">The password wasn't strong enough.</p>
 </div>
<?php }

if (isset($invalid_chars)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">The password contained invalid characters.</p>
 </div>
<?php }

if (isset($mismatched)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">The passwords didn't match.</p>
 </div>
<?php }

if (isset($missing_current_password)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">Current password is required.</p>
 </div>
<?php }

if (isset($invalid_current_password)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">Invalid current password.</p>
 </div>
<?php }

if (isset($password_in_history)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">Password was used recently and cannot be reused.</p>
 </div>
<?php }

if (isset($ppolicy_error)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">
  <?php
  if ($ppolicy_error == "SYSTEM_ERROR") {
    echo "The password could not be changed due to a system configuration issue. Please contact your administrator.";
  } else {
    echo "Password policy error: " . htmlspecialchars($ppolicy_error);
  }
  ?>
  </p>
 </div>
<?php }

// Display password expired alert
if ($password_expired_forced) {  ?>
 <div class="alert alert-danger">
  <p class="text-center"><strong>Your password has expired.</strong><br>
  You must change your password to continue using your account.</p>
 </div>
<?php }
// Display password expiry warning (if not already expired)
elseif ($password_expires_in_days !== null && $password_expires_in_days > 0 && $password_expires_in_days <= $PASSWORD_EXPIRY_WARNING_DAYS) { ?>
 <div class="alert alert-warning">
  <p class="text-center"><strong>Your password expires in <?php echo $password_expires_in_days; ?> day<?php echo $password_expires_in_days != 1 ? 's' : ''; ?>.</strong><br>
  Please change it now to avoid being locked out of your account.</p>
 </div>
<?php }

// Display password age information if available
if ($password_age_days !== null) { ?>
 <div class="alert alert-info">
  <p class="text-center">
  <strong>Password Information:</strong><br>
  Last changed: <?php echo $password_changed_formatted; ?> (<?php echo $password_age_days; ?> day<?php echo $password_age_days != 1 ? 's' : ''; ?> ago)
  <?php if ($password_expires_in_days !== null && $password_expires_in_days > 0) { ?>
  <br>Expires: <?php echo date('F j, Y', strtotime($password_changed_time) + ($PASSWORD_EXPIRY_DAYS * 86400)); ?>
  <?php } ?>
  </p>
 </div>
<?php }

?>
</div>
<?php

?>

<script src="<?php print url('/js/password-utils.js'); ?>"></script>

<div class="container">
 <div class="row justify-content-center">
  <div class="col-md-8">

   <div class="card">
   <div class="card-header text-center">Change your password</div>

   <ul class="list-group">
    <li class="list-group-item">Use this form to change your <?php print $ORGANISATION_NAME; ?> password.  When you start typing your new password the gauge at the bottom will show its security strength.
    Enter your password again in the <b>confirm</b> field.  If the passwords don't match then both fields will be bordered with red.</li>
   </ul>

   <div class="card-body text-center">

    <form class="form-horizontal" action='' method='post'>

     <input type='hidden' id="change_password" name="change_password">
     <input type='hidden' id="pass_score" value="0" name="pass_score">

     <?php if ($PPOLICY_ENABLED) { ?>
     <div class="row mb-3">
      <label for="current_password" class="col-sm-3 col-form-label text-end">Current Password</label>
      <div class="col-sm-6">
       <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
     </div>
     <?php } ?>

     <div class="row mb-3" id="password_div">
      <label for="password" class="col-sm-3 col-form-label text-end">New Password</label>
      <div class="col-sm-6">
       <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
      </div>
     </div>

     <script>
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
     </script>

     <div class="row mb-3" id="confirm_div">
      <label for="password" class="col-sm-3 col-form-label text-end">Confirm</label>
      <div class="col-sm-6">
       <input type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
      </div>
     </div>

     <div class="text-center mb-3">
       <button type="submit" class="btn btn-secondary">Change password</button>
     </div>
     
    </form>

    <?php if ($PASSWORD_POLICY_ENABLED) { ?>
    <!-- Password Requirements Checklist -->
    <div class="card mt-3">
      <div class="card-header"><small><strong>Password requirements</strong></small></div>
      <div class="card-body" id="PasswordRequirements">
        <!-- Requirements will be dynamically inserted here -->
      </div>
    </div>

    <script>
      // Initialise password requirements checker
      document.addEventListener('DOMContentLoaded', function() {
        window.passwordRequirements = {
          minLength: <?php echo (int)$PASSWORD_MIN_LENGTH; ?>,
          requireUppercase: <?php echo $PASSWORD_REQUIRE_UPPERCASE ? 'true' : 'false'; ?>,
          requireLowercase: <?php echo $PASSWORD_REQUIRE_LOWERCASE ? 'true' : 'false'; ?>,
          requireNumbers: <?php echo $PASSWORD_REQUIRE_NUMBERS ? 'true' : 'false'; ?>,
          requireSpecial: <?php echo $PASSWORD_REQUIRE_SPECIAL ? 'true' : 'false'; ?>
        };
        initPasswordRequirements('password', window.passwordRequirements);
      });
    </script>
    <?php } else { ?>
    <!-- Password Strength Meter (fallback when policy not enabled) -->
    <div class="progress mt-3">
      <div id="StrengthProgressBar" class="progress progress-bar"></div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        initPasswordStrength('password');
      });
    </script>
    <?php } ?>

   </div>
  </div>

  </div>
 </div>
</div>
<?php

render_footer();

?>

