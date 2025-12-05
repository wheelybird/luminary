<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "password_reset_functions.inc.php";
include_once "password_policy_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "mail_functions.inc.php";

// No authentication required for this page (public access)

// Check if password reset feature is enabled
if ($PASSWORD_RESET_ENABLED != TRUE) {
  header("Location: " . url('/log_in'));
  exit(0);
}

// Get token and username from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$username = isset($_GET['user']) ? $_GET['user'] : '';

if (empty($token) || empty($username)) {
  header("Location: " . url('/password_reset/request.php'));
  exit(0);
}

$ldap_connection = open_ldap_connection();

// Check if user is locked out
if (password_reset_check_lockout($ldap_connection, $username)) {
  $locked_out = TRUE;
  audit_log('password_reset_lockout_active', $username, 'Attempted access while locked out, IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'failure');
}

// Validate token
$token_valid = FALSE;
if (!isset($locked_out)) {
  $token_valid = password_reset_validate_token($ldap_connection, $username, $token);

  if (!$token_valid) {
    // Record failure
    $failure_count = password_reset_record_failure($ldap_connection, $username);

    if ($failure_count >= (isset($PASSWORD_RESET_MAX_ATTEMPTS) ? $PASSWORD_RESET_MAX_ATTEMPTS : 5)) {
      $locked_out = TRUE;
      audit_log('password_reset_lockout', $username, "Account locked after $failure_count failures, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'failure');
    }

    audit_log('password_reset_invalid_token', $username, 'Invalid or expired token, IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'failure');
  }
}

// Process password change
if (isset($_POST['change_password']) && $token_valid && !isset($locked_out)) {

  $password = $_POST['password'];

  // Validate password
  if (!$password) { $not_strong_enough = 1; }
  if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $not_strong_enough = 1; }
  if (preg_match("/\"|'/",$password)) { $invalid_chars = 1; }
  if ($_POST['password'] != $_POST['password_match']) { $mismatched = 1; }

  // Password policy validation if enabled
  $password_policy_errors = array();
  $password_fails_policy = false;
  if ($PASSWORD_POLICY_ENABLED == TRUE) {
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
  }

  if (!isset($mismatched) and !isset($not_strong_enough) and !isset($invalid_chars) and !$password_fails_policy) {

    // Change the password
    $password_changed = ldap_change_password($ldap_connection, $username, $password);

    if ($password_changed) {

      // Clean up token
      password_reset_cleanup_token($ldap_connection, $username);

      // Clear lockout if any
      password_reset_clear_lockout($ldap_connection, $username);

      // Get user details for email
      $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
        "({$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")",
        array('mail', 'givenname', 'sn'));

      if ($user_search) {
        $user_entry = ldap_get_entries($ldap_connection, $user_search);
        if ($user_entry['count'] > 0 && isset($user_entry[0]['mail'][0])) {
          $user_mail = $user_entry[0]['mail'][0];
          $user_givenname = isset($user_entry[0]['givenname'][0]) ? $user_entry[0]['givenname'][0] : '';
          $user_sn = isset($user_entry[0]['sn'][0]) ? $user_entry[0]['sn'][0] : '';
          $full_name = trim($user_givenname . " " . $user_sn);

          // Send confirmation email
          if ($EMAIL_SENDING_ENABLED == TRUE) {
            $timestamp = time();
            $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

            $mail_body = parse_mail_text(
              $password_reset_success_mail_body,
              '', // No password
              $username,
              $user_givenname,
              $user_sn,
              $timestamp,
              $ip_address,
              $ADMIN_EMAIL
            );

            $mail_subject = parse_mail_text(
              $password_reset_success_mail_subject,
              '',
              $username,
              $user_givenname,
              $user_sn,
              $timestamp,
              $ip_address,
              $ADMIN_EMAIL
            );

            send_email($user_mail, $full_name, $mail_subject, $mail_body);
          }
        }
      }

      // Audit log success
      audit_log('password_reset_success', $username, 'Password reset via self-service, IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'success');

      ldap_close($ldap_connection);

      // Redirect to login with success message
      render_header("$ORGANISATION_NAME - Password reset successful");
      ?>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-md-8">
            <div class="card border-success">
              <div class="card-header text-center bg-success text-white">Password reset successful</div>
              <div class="card-body text-center">
                <p>Your password has been successfully reset.</p>
                <p>You can now log in with your new password.</p>
                <p class="mt-3"><a href="<?php echo url('/log_in'); ?>" class="btn btn-primary">Go to login page</a></p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php
      render_footer();
      exit(0);
    }
    else {
      $password_change_failed = TRUE;
      audit_log('password_reset_change_failed', $username, 'LDAP password change failed, IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'failure');
    }
  }
}

ldap_close($ldap_connection);

render_header("Reset your $ORGANISATION_NAME password");

?>
<script src="<?php print url('/js/password-utils.js'); ?>"></script>

<div class="container">
<?php

if (isset($locked_out)) {  ?>
 <div class="alert alert-danger">
  <p class="text-center"><strong>Account temporarily locked</strong></p>
  <p class="text-center">Too many failed password reset attempts. Please try again later or request a new reset link.</p>
  <p class="text-center mt-3"><a href="<?php echo url('/password_reset/request.php'); ?>" class="btn btn-secondary">Request new reset link</a></p>
 </div>
<?php
  render_footer();
  exit(0);
}

if (!$token_valid) {  ?>
 <div class="alert alert-danger">
  <p class="text-center"><strong>Invalid or expired reset link</strong></p>
  <p class="text-center">This password reset link is no longer valid. It may have expired or already been used.</p>
  <p class="text-center">Password reset links expire after <?php echo isset($PASSWORD_RESET_TOKEN_EXPIRY_MINUTES) ? $PASSWORD_RESET_TOKEN_EXPIRY_MINUTES : 60; ?> minutes and can only be used once.</p>
  <p class="text-center mt-3"><a href="<?php echo url('/password_reset/request.php'); ?>" class="btn btn-secondary">Request new reset link</a></p>
 </div>
<?php
  render_footer();
  exit(0);
}

// Token is valid - show password change form

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

if (isset($password_fails_policy)) {  ?>
 <div class="alert alert-warning">
  <p class="text-center"><strong>Password does not meet requirements:</strong></p>
  <ul class="text-start">
   <?php foreach ($password_policy_errors as $error) { ?>
    <li><?php echo htmlspecialchars($error); ?></li>
   <?php } ?>
  </ul>
 </div>
<?php }

if (isset($password_change_failed)) {  ?>
 <div class="alert alert-danger">
  <p class="text-center">Failed to change password. Please try again or contact the administrator.</p>
 </div>
<?php }

?>

 <div class="row justify-content-center">
  <div class="col-md-8">

   <div class="card">
    <div class="card-header text-center">Reset your password</div>

    <ul class="list-group">
     <li class="list-group-item">Enter your new password below. When you start typing your new password the gauge at the bottom will show its security strength. Enter your password again in the <b>confirm</b> field.</li>
    </ul>

    <div class="card-body text-center">

     <form class="form-horizontal" action='' method='post'>

      <input type='hidden' name="change_password" value="1">
      <input type='hidden' id="pass_score" value="0" name="pass_score">

      <div class="row mb-3" id="password_div">
       <label for="password" class="col-sm-3 col-form-label text-end">New password</label>
       <div class="col-sm-6">
        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
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
       <label for="confirm" class="col-sm-3 col-form-label text-end">Confirm password</label>
       <div class="col-sm-6">
        <input type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()" required>
       </div>
      </div>

      <div class="text-center mb-3">
        <button type="submit" class="btn btn-primary">Reset password</button>
      </div>

     </form>

     <div class="text-center mt-3">
       <a href="<?php echo url('/log_in'); ?>">Back to login</a>
     </div>

    </div>
   </div>

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
   <div class="card mt-3">
     <div class="card-header"><small><strong>Password strength</strong></small></div>
     <div class="card-body">
       <div class="progress">
         <div id="StrengthProgressBar" class="progress progress-bar"></div>
       </div>
     </div>
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
<?php

render_footer();

?>
