<?php

/**
 * User Password Tab
 * Displays password expiry information
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

// Get password change time and calculate expiry info
include_once "password_policy_functions.inc.php";

$password_changed_time = password_policy_get_changed_time($ldap_connection, $dn);

// Calculate password info if available
$password_changed_formatted = null;
$password_age_days = null;
$password_expires_in_days = null;
$expiry_date = null;
$status_badge = 'bg-secondary';
$status_text = 'Unknown';

if ($password_changed_time) {
  // Calculate password age and expiry
  $timestamp = strtotime($password_changed_time);
  $password_changed_formatted = date('F j, Y \a\t g:i A', $timestamp);
  $age_seconds = time() - $timestamp;
  $password_age_days = floor($age_seconds / 86400);
  $password_expires_in_days = $PASSWORD_EXPIRY_DAYS - $password_age_days;
  $expiry_date = date('F j, Y', $timestamp + ($PASSWORD_EXPIRY_DAYS * 86400));

  // Determine status
  $status_badge = 'bg-success';
  $status_text = 'OK';
  if ($password_expires_in_days <= 0) {
    $status_badge = 'bg-danger';
    $status_text = 'Expired';
  } elseif ($password_expires_in_days <= $PASSWORD_EXPIRY_WARNING_DAYS) {
    $status_badge = 'bg-warning text-dark';
    $status_text = 'Expiring Soon';
  }
}
?>
<?php if ($password_changed_time): ?>
<table class="table table-condensed">
  <tr>
    <th width="30%">Password Status:</th>
    <td>
      <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
    </td>
  </tr>
  <tr>
    <th>Last Changed:</th>
    <td><?php echo $password_changed_formatted; ?></td>
  </tr>
  <tr>
    <th>Password Age:</th>
    <td><?php echo $password_age_days; ?> day<?php echo $password_age_days != 1 ? 's' : ''; ?></td>
  </tr>
  <tr>
    <th>Expiry Date:</th>
    <td><?php echo $expiry_date; ?></td>
  </tr>
  <tr>
    <th>Days Until Expiry:</th>
    <td>
      <?php if ($password_expires_in_days > 0): ?>
        <span class="text-<?php echo $status_badge == 'bg-warning text-dark' ? 'warning' : 'success'; ?>">
          <?php echo $password_expires_in_days; ?> day<?php echo $password_expires_in_days != 1 ? 's' : ''; ?>
        </span>
      <?php else: ?>
        <span class="text-danger">Password has expired</span>
      <?php endif; ?>
    </td>
  </tr>
</table>
<?php else: ?>
<div class="alert alert-info">
  <p><strong><i class="bi bi-info-circle"></i> Password Information Not Available</strong></p>
  <p>Password expiry tracking requires the ppolicy overlay to be configured on the LDAP server and the user to have changed their password at least once.</p>
</div>
<?php endif; ?>
