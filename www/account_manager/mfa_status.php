<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager - MFA Status");
render_submenu();

$ldap_connection = open_ldap_connection();

// Handle admin actions
if (isset($_POST['disable_user_mfa'])) {
  $username = $_POST['username'];

  // Get user DN
  $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
    "({$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")",
    array('dn'));

  if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
      $user_dn = $user_entry[0]['dn'];

      if (totp_disable($ldap_connection, $user_dn)) {
        render_alert_banner("MFA disabled for user <strong>$username</strong>.");
      } else {
        render_alert_banner("Failed to disable MFA for user <strong>$username</strong>.", "danger", 15000);
      }
    }
  }
}

if (isset($_POST['reset_grace_period'])) {
  $username = $_POST['username'];

  // Get user DN
  $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
    "({$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")",
    array('dn'));

  if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
      $user_dn = $user_entry[0]['dn'];

      // Set status to pending and reset enrolled date
      $modifications = array(
        'totpStatus' => 'pending',
        'totpEnrolledDate' => gmdate('YmdHis') . 'Z',
      );

      if (ldap_mod_replace($ldap_connection, $user_dn, $modifications)) {
        render_alert_banner("Grace period reset for user <strong>$username</strong>.");
      } else {
        render_alert_banner("Failed to reset grace period for user <strong>$username</strong>.", "danger", 15000);
      }
    }
  }
}

// Get all users with MFA status
$people = ldap_get_user_list($ldap_connection);

// Enhance with MFA status information
$mfa_stats = array(
  'total' => 0,
  'active' => 0,
  'pending' => 0,
  'disabled' => 0,
  'not_configured' => 0,
  'required' => 0,
  'grace_expired' => 0,
);

foreach ($people as $username => $attribs) {
  $mfa_stats['total']++;

  // Get MFA status
  $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
    "({$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")",
    array('totpStatus', 'totpEnrolledDate', 'memberOf'));

  if ($user_search) {
    $user_entry = ldap_get_entries($ldap_connection, $user_search);
    if ($user_entry['count'] > 0) {
      $status = isset($user_entry[0]['totpstatus'][0]) ? $user_entry[0]['totpstatus'][0] : 'none';
      $enrolled_date = isset($user_entry[0]['totpenrolleddate'][0]) ? $user_entry[0]['totpenrolleddate'][0] : null;

      $people[$username]['mfa_status'] = $status;
      $people[$username]['mfa_enrolled_date'] = $enrolled_date;

      // Check if user requires MFA
      $requires_mfa = totp_user_requires_mfa($ldap_connection, $username, $MFA_REQUIRED_GROUPS);
      $people[$username]['mfa_required'] = $requires_mfa;

      // Calculate grace period
      if ($status == 'pending' && $enrolled_date && $requires_mfa) {
        $days_remaining = totp_grace_period_remaining($enrolled_date, $MFA_GRACE_PERIOD_DAYS);
        $people[$username]['grace_days_remaining'] = $days_remaining;

        if ($days_remaining <= 0) {
          $mfa_stats['grace_expired']++;
        }
      }

      // Update statistics
      switch ($status) {
        case 'active':
          $mfa_stats['active']++;
          break;
        case 'pending':
          $mfa_stats['pending']++;
          break;
        case 'disabled':
          $mfa_stats['disabled']++;
          break;
        default:
          $mfa_stats['not_configured']++;
      }

      if ($requires_mfa) {
        $mfa_stats['required']++;
      }
    }
  }
}

?>

<div class="container">

  <h2>MFA Status Overview</h2>

  <!-- Statistics Cards -->
  <div class="row" style="margin-bottom: 20px;">
    <div class="col-md-2">
      <div class="panel panel-default">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['total']; ?></h3>
          <p>Total Users</p>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="panel panel-success">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['active']; ?></h3>
          <p>Active</p>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="panel panel-warning">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['pending']; ?></h3>
          <p>Pending</p>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="panel panel-default">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['not_configured']; ?></h3>
          <p>Not Configured</p>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="panel panel-info">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['required']; ?></h3>
          <p>Required</p>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="panel panel-danger">
        <div class="panel-body text-center">
          <h3><?php echo $mfa_stats['grace_expired']; ?></h3>
          <p>Grace Expired</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Configuration Summary -->
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">MFA Configuration</h4>
    </div>
    <div class="panel-body">
      <table class="table table-condensed">
        <tr>
          <th>MFA Enabled:</th>
          <td><?php echo $MFA_ENABLED ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>'; ?></td>
        </tr>
        <?php if ($MFA_ENABLED && !empty($MFA_REQUIRED_GROUPS)) { ?>
          <tr>
            <th>Required Groups:</th>
            <td><?php echo implode(', ', $MFA_REQUIRED_GROUPS); ?></td>
          </tr>
          <tr>
            <th>Grace Period:</th>
            <td><?php echo $MFA_GRACE_PERIOD_DAYS; ?> days</td>
          </tr>
          <tr>
            <th>TOTP Issuer:</th>
            <td><?php echo htmlspecialchars($MFA_TOTP_ISSUER); ?></td>
          </tr>
        <?php } ?>
      </table>
    </div>
  </div>

  <!-- User List -->
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">User MFA Status</h4>
    </div>
    <div class="panel-body">
      <input class="form-control" id="search_input" type="text" placeholder="Search users...">
    </div>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Username</th>
          <th>MFA Status</th>
          <th>Required</th>
          <th>Grace Period</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="userlist">
        <script>
          $(document).ready(function(){
            $("#search_input").on("keyup", function() {
              var value = $(this).val().toLowerCase();
              $("#userlist tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
              });
            });
          });
        </script>
        <?php foreach ($people as $username => $attribs) { ?>
          <tr>
            <td>
              <a href="<?php echo $THIS_MODULE_PATH; ?>/show_user.php?account_identifier=<?php echo urlencode($username); ?>">
                <?php echo htmlspecialchars($username); ?>
              </a>
            </td>
            <td>
              <?php
                $status = isset($attribs['mfa_status']) ? $attribs['mfa_status'] : 'none';
                switch ($status) {
                  case 'active':
                    echo '<span class="label label-success">Active</span>';
                    break;
                  case 'pending':
                    echo '<span class="label label-warning">Pending</span>';
                    break;
                  case 'disabled':
                    echo '<span class="label label-default">Disabled</span>';
                    break;
                  default:
                    echo '<span class="label label-default">Not Configured</span>';
                }
              ?>
            </td>
            <td>
              <?php
                if (isset($attribs['mfa_required']) && $attribs['mfa_required']) {
                  echo '<span class="label label-info">Yes</span>';
                } else {
                  echo '<span class="label label-default">No</span>';
                }
              ?>
            </td>
            <td>
              <?php
                if (isset($attribs['grace_days_remaining'])) {
                  $days = $attribs['grace_days_remaining'];
                  if ($days > 3) {
                    echo '<span class="label label-success">' . $days . ' days</span>';
                  } elseif ($days > 0) {
                    echo '<span class="label label-warning">' . $days . ' days</span>';
                  } else {
                    echo '<span class="label label-danger">Expired</span>';
                  }
                } else {
                  echo '<span class="text-muted">N/A</span>';
                }
              ?>
            </td>
            <td>
              <?php if ($status == 'active') { ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                  <button type="submit" name="disable_user_mfa" class="btn btn-xs btn-danger"
                          onclick="return confirm('Disable MFA for <?php echo htmlspecialchars($username); ?>?');">
                    Disable MFA
                  </button>
                </form>
              <?php } ?>

              <?php if ($status == 'pending' && isset($attribs['grace_days_remaining']) && $attribs['grace_days_remaining'] <= 0) { ?>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                  <button type="submit" name="reset_grace_period" class="btn btn-xs btn-warning"
                          onclick="return confirm('Reset grace period for <?php echo htmlspecialchars($username); ?>?');">
                    Reset Grace Period
                  </button>
                </form>
              <?php } ?>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

</div>

<?php
ldap_close($ldap_connection);
render_footer();
?>
