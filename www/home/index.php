<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";

set_page_access("user");

// Check user's MFA status
$ldap_connection = open_ldap_connection();
$mfa_status = array('needs_setup' => false);

if ($MFA_FULLY_OPERATIONAL && !empty($MFA_REQUIRED_GROUPS)) {
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

  <?php if ($IS_ADMIN): ?>
  <?php
  // Check MFA schema status dynamically for admin dashboard
  $ldap_conn = open_ldap_connection();
  $rfc2307bis = ldap_detect_rfc2307bis($ldap_conn);

  // Check TOTP schema if MFA is enabled
  if ($MFA_ENABLED == TRUE) {
    $MFA_SCHEMA_OK = totp_check_schema($ldap_conn);
    $MFA_FULLY_OPERATIONAL = $MFA_SCHEMA_OK;
  }

  ldap_close($ldap_conn);
  ?>
  <div class="row" style="margin-top: 30px;">
    <div class="col-md-12">
      <div class="panel panel-info">
        <div class="panel-heading">
          <h3 class="panel-title">System Status</h3>
        </div>
        <div class="panel-body">
          <div class="row">
            <div class="col-md-6">
              <h4>LDAP Configuration</h4>
              <table class="table table-condensed">
                <tr>
                  <th style="width: 40%;">LDAP URI:</th>
                  <td><?php echo htmlspecialchars($LDAP['uri']); ?></td>
                </tr>
                <tr>
                  <th>Base DN:</th>
                  <td><?php echo htmlspecialchars($LDAP['base_dn']); ?></td>
                </tr>
                <tr>
                  <th>RFC2307bis:</th>
                  <td>
                    <?php if ($rfc2307bis): ?>
                      <span class="label label-success">Enabled</span>
                    <?php else: ?>
                      <span class="label label-default">Disabled</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </table>

              <h4>Email Configuration</h4>
              <table class="table table-condensed">
                <tr>
                  <th style="width: 40%;">SMTP:</th>
                  <td>
                    <?php if ($EMAIL_SENDING_ENABLED): ?>
                      <span class="label label-success">Configured</span>
                      <br><small><?php echo htmlspecialchars($SMTP['host'] . ':' . $SMTP['port']); ?></small>
                    <?php else: ?>
                      <span class="label label-default">Not Configured</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <th>Account Requests:</th>
                  <td>
                    <?php if ($ACCOUNT_REQUESTS_ENABLED): ?>
                      <span class="label label-success">Enabled</span>
                    <?php else: ?>
                      <span class="label label-default">Disabled</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
            </div>

            <div class="col-md-6">
              <h4>Multi-Factor Authentication</h4>
              <table class="table table-condensed">
                <tr>
                  <th style="width: 40%;">MFA Status:</th>
                  <td>
                    <?php if ($MFA_ENABLED): ?>
                      <span class="label label-info">Enabled</span>
                    <?php else: ?>
                      <span class="label label-default">Disabled</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ($MFA_ENABLED): ?>
                <tr>
                  <th>TOTP Schema:</th>
                  <td>
                    <?php if ($MFA_SCHEMA_OK): ?>
                      <span class="label label-success">OK</span>
                      <br><small>Object class: <?php echo htmlspecialchars($TOTP_ATTRS['objectclass']); ?></small>
                    <?php else: ?>
                      <span class="label label-warning">Missing</span>
                      <br><small class="text-warning">Schema not found in LDAP</small>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if (!$MFA_SCHEMA_OK): ?>
                <tr>
                  <td colspan="2">
                    <div class="alert alert-warning" style="margin-bottom: 0;">
                      <strong>Warning:</strong> MFA is enabled but the TOTP schema is not installed in LDAP.
                      <br>Users will not be able to enrol in MFA until the schema is added.
                      <br><br>
                      <strong>To resolve:</strong>
                      <ol>
                        <li>Install the TOTP schema from <a href="https://github.com/wheelybird/ldap-totp-schema" target="_blank">ldap-totp-schema repository</a></li>
                        <li>Verify installation with:<br>
                          <code style="display: block; margin: 5px 0; padding: 5px; background: #f5f5f5;">ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=schema,cn=config "(cn=*totp*)"</code>
                        </li>
                        <li>Restart this container to re-check the schema</li>
                      </ol>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>
                <?php if ($MFA_FULLY_OPERATIONAL && !empty($MFA_REQUIRED_GROUPS)): ?>
                <tr>
                  <th>Required Groups:</th>
                  <td>
                    <?php foreach ($MFA_REQUIRED_GROUPS as $group): ?>
                      <span class="label label-info"><?php echo htmlspecialchars($group); ?></span>
                    <?php endforeach; ?>
                  </td>
                </tr>
                <tr>
                  <th>Grace Period:</th>
                  <td><?php echo $MFA_GRACE_PERIOD_DAYS; ?> days</td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
              </table>

              <h4>System Information</h4>
              <table class="table table-condensed">
                <tr>
                  <th style="width: 40%;">PHP Version:</th>
                  <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                  <th>Session Timeout:</th>
                  <td><?php echo $SESSION_TIMEOUT; ?> minutes</td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php

render_footer();

?>
