<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";

set_page_access("user");

// Check user's MFA status
$ldap_connection = open_ldap_connection();
$mfa_status = array('needs_setup' => false);

if ($MFA_ENABLED && !empty($MFA_REQUIRED_GROUPS)) {
  include_once "totp_functions.inc.php";
  $mfa_status = totp_get_user_mfa_status($ldap_connection, $USER_ID, $MFA_REQUIRED_GROUPS, $MFA_GRACE_PERIOD_DAYS);
}

ldap_close($ldap_connection);

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

  <div class="row">
    <div class="col-md-12">
      <h2>Welcome<?php if(isset($USER_ID)) { echo ', ' . htmlspecialchars($USER_ID); } ?></h2>
      <p class="lead">Select an option below to manage your account.</p>
    </div>
  </div>

  <div class="row">

    <?php if (in_array('change_password', array_keys($MODULES)) && $MODULES['change_password'] == 'auth') { ?>
    <div class="col-md-6">
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">Change Password</h3>
        </div>
        <div class="panel-body">
          <p>Update your account password.</p>
          <a href="<?php echo $SERVER_PATH; ?>change_password" class="btn btn-primary">Change Password</a>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php if (isset($MODULES['manage_mfa']) && $MODULES['manage_mfa'] == 'auth') { ?>
    <div class="col-md-6">
      <div class="panel panel-<?php echo $mfa_status['needs_setup'] ? 'warning' : 'default'; ?>">
        <div class="panel-heading">
          <h3 class="panel-title">
            Manage MFA
            <?php if ($mfa_status['needs_setup']): ?>
              <span class="label label-warning pull-right">Required</span>
            <?php endif; ?>
          </h3>
        </div>
        <div class="panel-body">
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
      <div class="panel panel-default">
        <div class="panel-heading">
          <h3 class="panel-title">Account Manager</h3>
        </div>
        <div class="panel-body">
          <p>Manage user accounts, groups, and system settings.</p>
          <a href="<?php echo $SERVER_PATH; ?>account_manager" class="btn btn-success">Account Manager</a>
        </div>
      </div>
    </div>
    <?php } ?>

  </div>
</div>

<?php

render_footer();

?>
