<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include "web_functions.inc.php";
include "ldap_functions.inc.php";
include "totp_functions.inc.php";
include "audit_functions.inc.php";

// Check for temp auth cookie (only accessible if redirected from login with pending MFA validation)
if (!isset($_COOKIE['mfa_temp_auth'])) {
  header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in?session_timeout\n\n");
  exit;
}

$temp_auth_key = $_COOKIE['mfa_temp_auth'];
$temp_filename = preg_replace('/[^a-zA-Z0-9]/','_', "mfa_pending_" . $temp_auth_key);
$temp_file_path = "/tmp/$temp_filename";

// Load temp session data
if (!file_exists($temp_file_path)) {
  header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in?session_timeout\n\n");
  exit;
}

$temp_data = json_decode(file_get_contents($temp_file_path), true);

// Check if session expired (5 minutes)
if ((time() - $temp_data['timestamp']) > 300) {
  @ unlink($temp_file_path);
  setcookie('mfa_temp_auth', '', time() - 3600, '/');
  header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in?session_timeout\n\n");
  exit;
}

$account_id = $temp_data['user_id'];
$is_admin = $temp_data['is_admin'];
$redirect_to = $temp_data['redirect_to'];

// Handle TOTP code submission
if (isset($_POST['totp_code'])) {

  $totp_code = trim($_POST['totp_code']);

  // Validate TOTP code format (6 digits)
  if (!preg_match('/^\d{6}$/', $totp_code)) {
    $error_message = "Please enter a valid 6-digit code.";
  }
  else {
    // Connect to LDAP and get user's TOTP secret
    $ldap_connection = open_ldap_connection();

    $secret_attr = $TOTP_ATTRS['secret'];
    $secret_attr_lower = strtolower($secret_attr);

    $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
      "({$LDAP['account_attribute']}=" . ldap_escape($account_id, "", LDAP_ESCAPE_FILTER) . ")",
      array($secret_attr));

    if ($user_search) {
      $user_entry = ldap_get_entries($ldap_connection, $user_search);

      if ($user_entry['count'] > 0 && isset($user_entry[0][$secret_attr_lower][0])) {
        $totp_secret = $user_entry[0][$secret_attr_lower][0];

        // Validate TOTP code (with window of 1 = ±30 seconds)
        if (totp_validate_code($totp_secret, $totp_code, 1, 30)) {
          // TOTP code valid - complete login
          ldap_close($ldap_connection);

          // Clean up temp session
          @ unlink($temp_file_path);
          setcookie('mfa_temp_auth', '', time() - 3600, '/');

          // Audit log successful MFA verification
          audit_log('mfa_verify_success', $account_id, 'TOTP code verified during login', 'success', $account_id);

          // Set the real session cookie
          set_passkey_cookie($account_id, $is_admin);

          // Redirect to appropriate destination
          if ($redirect_to) {
            header("Location: //{$_SERVER['HTTP_HOST']}" . base64_decode($redirect_to) . "\n\n");
          }
          else {
            $default_module = $is_admin ? "account_manager" : "home";
            header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}$default_module?logged_in\n\n");
          }
          exit;
        }
        else {
          // Invalid TOTP code
          audit_log('mfa_verify_failure', $account_id, 'Invalid TOTP code during login', 'failure', $account_id);
          $error_message = "Invalid verification code. Please try again.";
        }
      }
      else {
        // No TOTP secret found
        $error_message = "MFA configuration error. Please contact your administrator.";
      }
    }
    else {
      $error_message = "Unable to verify code. Please try again.";
    }

    ldap_close($ldap_connection);
  }
}

// Render the TOTP validation page
render_header("$ORGANISATION_NAME account manager - Verify MFA");

?>
<div class="container">
 <div class="row justify-content-center">
  <div class="col-sm-6">

   <div class="card">
   <div class="card-header text-center">
     <h5 class="mb-0">Multi-Factor Authentication</h5>
   </div>
   <div class="card-body">

   <?php if (isset($error_message)) { ?>
   <div class="alert alert-danger">
    <?php echo htmlspecialchars($error_message); ?>
   </div>
   <?php } ?>

   <p class="text-center mb-4">
     Enter the 6-digit verification code from your authenticator app.
   </p>

   <form class="form-horizontal" action="" method="post">

    <div class="row mb-3">
     <label for="totp_code" class="col-sm-4 col-form-label">Verification Code</label>
     <div class="col-sm-8">
      <input type="text"
             class="form-control form-control-lg text-center"
             id="totp_code"
             name="totp_code"
             maxlength="6"
             pattern="\d{6}"
             inputmode="numeric"
             autocomplete="one-time-code"
             autofocus
             required>
      <small class="form-text text-muted">Enter the 6-digit code from your app</small>
     </div>
    </div>

    <div class="row mb-3">
     <div class="col-sm-12 text-center">
      <button type="submit" class="btn btn-primary btn-lg">Verify</button>
     </div>
    </div>

    <div class="row">
     <div class="col-sm-12 text-center">
      <a href="/<?php echo $SERVER_PATH; ?>log_in" class="btn btn-link">Cancel and log out</a>
     </div>
    </div>

   </form>

   <hr>

   <div class="alert alert-info mb-0">
    <strong>Note:</strong> This verification code expires in 5 minutes.
    If you need help, please contact your administrator.
   </div>

   </div>
  </div>
 </div>
</div>

<?php
render_footer();
?>
