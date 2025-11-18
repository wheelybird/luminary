<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";

// Include TOTP functions if MFA is enabled (needed for schema check)
if ($MFA_FEATURE_ENABLED == TRUE) {
  include_once "totp_functions.inc.php";
}

set_page_access("user");

// Open LDAP connection for all checks
$ldap_connection = open_ldap_connection();

// Check MFA schema status dynamically if MFA is enabled
if ($MFA_FEATURE_ENABLED == TRUE) {
  $MFA_SCHEMA_OK = totp_check_schema($ldap_connection);
  $MFA_FULLY_OPERATIONAL = $MFA_SCHEMA_OK;
}

// Check user's MFA status
$mfa_status = array('needs_setup' => false);

if ($MFA_FULLY_OPERATIONAL) {
  $mfa_status = totp_get_user_mfa_status($ldap_connection, $USER_ID, $MFA_REQUIRED_GROUPS, $MFA_GRACE_PERIOD_DAYS);
}

render_header("$ORGANISATION_NAME account manager");

?>

<div class="container">

  <?php if ($mfa_status['needs_setup']): ?>
  <div class="alert alert-warning">
    <h4><strong>Action Required: Multi-Factor Authentication</strong></h4>
    <p>
      Your account requires multi-factor authentication (MFA) to be set up.
      <?php if ($mfa_status['days_remaining'] !== null): ?>
        You have <strong><?php echo $mfa_status['days_remaining']; ?> day<?php echo $mfa_status['days_remaining'] != 1 ? 's' : ''; ?> remaining</strong> to complete this setup.
      <?php endif; ?>
    </p>
    <p>Please click on "Manage MFA" below to set up your authenticator app.</p>
  </div>
  <?php endif; ?>

  <?php
  // Check password expiry status
  if (isset($_SESSION['password_should_warn']) && $_SESSION['password_should_warn'] === true && isset($_SESSION['password_days_remaining'])):
    $days_remaining = $_SESSION['password_days_remaining'];
  ?>
  <div class="alert alert-warning">
    <h4><strong>Password Expiry Warning</strong></h4>
    <p>
      Your password expires in <strong><?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?></strong>.
      Please change it now to avoid being locked out of your account.
    </p>
    <a href="<?php echo $SERVER_PATH; ?>change_password" class="btn btn-warning">Change Password Now</a>
  </div>
  <?php endif; ?>

  <?php
  // Check account expiration status
  if (isset($_SESSION['account_should_warn']) && $_SESSION['account_should_warn'] === true && isset($_SESSION['account_days_remaining'])):
    $account_days_remaining = $_SESSION['account_days_remaining'];
  ?>
  <div class="alert alert-danger">
    <h4><strong>Account Expiring Soon</strong></h4>
    <p>
      Your account will expire in <strong><?php echo $account_days_remaining; ?> day<?php echo $account_days_remaining != 1 ? 's' : ''; ?></strong>.
      Please contact your system administrator to request an account extension.
    </p>
    <?php if (!empty($SUPPORT_EMAIL)): ?>
    <p><strong>Support Contact:</strong> <a href="mailto:<?php echo htmlspecialchars($SUPPORT_EMAIL); ?>" class="text-white"><u><?php echo htmlspecialchars($SUPPORT_EMAIL); ?></u></a></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-md-12">
      <h2>Welcome<?php if(isset($USER_ID)) { echo ', ' . htmlspecialchars($USER_ID); } ?></h2>
      <p class="lead">Select an option below to manage your account.</p>
    </div>
  </div>

  <div class="row">

    <?php if (in_array('change_password', array_keys($MODULES)) && $MODULES['change_password'] == 'auth') { ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h3>Change Password</h3>
        </div>
        <div class="card-body">
          <p>Update your account password.</p>
          <a href="<?php echo $SERVER_PATH; ?>change_password" class="btn btn-primary">Change Password</a>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if (isset($MODULES['manage_mfa']) && $MODULES['manage_mfa'] == 'auth') { ?>
    <div class="col-md-6">
      <div class="card <?php echo $mfa_status['needs_setup'] ? 'border-warning' : ''; ?>">
        <div class="card-header <?php echo $mfa_status['needs_setup'] ? 'bg-warning text-dark' : ''; ?>">
          <h3>
            Manage MFA
            <?php if ($mfa_status['needs_setup']): ?>
              <span class="badge bg-warning text-dark float-end">Required</span>
            <?php endif; ?>
          </h3>
        </div>
        <div class="card-body">
          <?php if ($mfa_status['needs_setup']): ?>
            <p><strong>Action Required:</strong> Set up multi-factor authentication for your account.</p>
          <?php else: ?>
            <p>Set up or manage multi-factor authentication (MFA) for your account.</p>
          <?php endif; ?>
          <a href="<?php echo $SERVER_PATH; ?>manage_mfa" class="btn btn-<?php echo $mfa_status['needs_setup'] ? 'warning' : 'primary'; ?>">Manage MFA</a>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if (isset($MODULES['account_manager']) && ($MODULES['account_manager'] == 'admin' && $IS_ADMIN)) { ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h3>Account Manager</h3>
        </div>
        <div class="card-body">
          <p>Manage user accounts, groups, and system settings.</p>
          <a href="<?php echo $SERVER_PATH; ?>account_manager" class="btn btn-success">Account Manager</a>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if (isset($MODULES['system_config']) && ($MODULES['system_config'] == 'admin' && $IS_ADMIN)) { ?>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h3>System Config</h3>
        </div>
        <div class="card-body">
          <p>View complete system configuration and settings.</p>
          <a href="<?php echo $SERVER_PATH; ?>system_config" class="btn btn-info">System Config</a>
        </div>
      </div>
    </div>
    <?php } ?>

  </div>

</div>

<?php

render_footer();

?>
