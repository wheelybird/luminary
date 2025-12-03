<?php

/**
 * User MFA Tab
 * Displays MFA status and backup code management
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<table class="table table-condensed">
  <tr>
    <th width="30%">MFA Status:</th>
    <td>
      <?php
        switch ($user_totp_status) {
          case 'active':
            echo '<span class="badge bg-success">Active</span>';
            break;
          case 'pending':
            echo '<span class="badge bg-warning text-dark">Pending Setup</span>';
            break;
          case 'disabled':
            echo '<span class="badge bg-secondary">Disabled</span>';
            break;
          default:
            echo '<span class="badge bg-secondary">Not Configured</span>';
        }
      ?>
    </td>
  </tr>
  <?php if ($user_requires_mfa) { ?>
  <tr>
    <th>MFA Required:</th>
    <td><span class="badge bg-info text-dark">Yes</span> (Required by group membership)</td>
  </tr>
  <?php } ?>
  <?php if ($user_totp_status == 'active' && $user_backup_code_count > 0) { ?>
  <tr>
    <th>Backup Codes:</th>
    <td>
      <span class="badge <?php echo $user_backup_code_count < 3 ? 'bg-warning text-dark' : 'bg-info text-dark'; ?>">
        <?php echo $user_backup_code_count; ?> remaining
      </span>
      <?php if ($user_backup_code_count < 3) { ?>
        <span class="text-warning"><small> - Running low</small></span>
      <?php } ?>
    </td>
  </tr>
  <?php } ?>
</table>

<?php if ($user_totp_status == 'active') { ?>
<?php if (!$MFA_SCHEMA_OK) { ?>
  <div class="alert alert-warning" style="margin-top: 15px;">
    <strong>MFA Schema Missing:</strong> Backup code regeneration is unavailable because the TOTP schema is not installed in LDAP. See the System Status panel on the home page for details.
  </div>
<?php } else { ?>
<form method="post" style="margin-top: 15px;">
  <input type="hidden" name="account_identifier" value="<?php echo htmlspecialchars($account_identifier); ?>">
  <input type="hidden" name="regenerate_backup_codes" value="1">
  <button type="submit" class="btn btn-warning" onclick="return confirm('This will generate new backup codes and invalidate any existing unused codes. Continue?');">
    Regenerate Backup Codes
  </button>
</form>
<?php } ?>
<?php } ?>
