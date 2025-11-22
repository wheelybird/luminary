<?php

/**
 * User Lifecycle Tab
 * Displays account lifecycle information and admin controls
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<table class="table table-condensed">
  <tr>
    <th width="30%">Account Status:</th>
    <td>
      <span class="badge <?php echo $account_status_badge; ?>"><?php echo $account_status_text; ?></span>
    </td>
  </tr>
  <?php if ($account_created_formatted): ?>
  <tr>
    <th>Account Created:</th>
    <td><?php echo $account_created_formatted; ?></td>
  </tr>
  <?php endif; ?>
  <?php if ($account_expiry_date_formatted !== null): ?>
  <tr>
    <th>Expiration Date:</th>
    <td><?php echo $account_expiry_date_formatted; ?></td>
  </tr>
  <tr>
    <th>Days Until Expiry:</th>
    <td>
      <?php if ($account_is_expired): ?>
        <span class="text-danger">Account expired <?php echo abs($account_days_remaining); ?> day<?php echo abs($account_days_remaining) != 1 ? 's' : ''; ?> ago</span>
      <?php elseif ($account_days_remaining !== null && $account_days_remaining > 0): ?>
        <span class="text-<?php echo $account_status_badge == 'bg-warning text-dark' ? 'warning' : 'success'; ?>">
          <?php echo $account_days_remaining; ?> day<?php echo $account_days_remaining != 1 ? 's' : ''; ?>
        </span>
      <?php endif; ?>
    </td>
  </tr>
  <?php else: ?>
  <tr>
    <th>Expiration Date:</th>
    <td><em>No expiration date set</em></td>
  </tr>
  <?php endif; ?>
  <?php if ($PPOLICY_ENABLED == TRUE): ?>
  <tr>
    <th>Account Locked:</th>
    <td>
      <?php if ($account_is_locked): ?>
        <span class="badge bg-danger">Yes</span>
      <?php else: ?>
        <span class="badge bg-success">No</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endif; ?>
</table>

<!-- Admin controls for setting expiration -->
<div class="mt-3">
  <form method="POST" action="" id="set_account_expiry_form">
    <input type="hidden" name="account_identifier" value="<?php echo htmlspecialchars($account_identifier); ?>">
    <div class="row mb-3">
      <label for="account_expiry_date" class="col-sm-4 col-form-label text-end">Set Expiration Date:</label>
      <div class="col-sm-8">
        <div class="input-group">
          <input type="date" class="form-control" id="account_expiry_date" name="account_expiry_date"
                 value="<?php echo $account_expiry_timestamp ? date('Y-m-d', $account_expiry_timestamp) : ''; ?>">
          <button type="submit" name="update_account_expiry" class="btn btn-primary">Update</button>
          <?php if ($account_expiry_timestamp !== null): ?>
          <button type="submit" name="remove_account_expiry" class="btn btn-danger">Remove</button>
          <?php endif; ?>
        </div>
        <small class="form-text text-muted">Leave blank or click "Remove" to set no expiration</small>
      </div>
    </div>
  </form>

  <?php if ($PPOLICY_ENABLED == TRUE && $account_is_locked): ?>
  <form method="POST" action="" id="unlock_account_form" class="mt-2">
    <input type="hidden" name="account_identifier" value="<?php echo htmlspecialchars($account_identifier); ?>">
    <button type="submit" name="unlock_account" class="btn btn-warning">
      <i class="bi bi-unlock"></i> Unlock Account
    </button>
  </form>
  <?php endif; ?>
</div>
