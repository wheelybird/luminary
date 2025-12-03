<?php

/**
 * User Details Tab
 * Displays account attribute form and password fields
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<form class="form-horizontal" action="" enctype="multipart/form-data" method="post">

  <input type="hidden" name="update_account">
  <input type="hidden" id="pass_score" value="0" name="pass_score">
  <input type="hidden" name="account_identifier" value="<?php print $account_identifier; ?>">

  <?php
    foreach ($attribute_map as $attribute => $attr_r) {
      $label = $attr_r['label'];
      if (isset($attr_r['onkeyup'])) { $onkeyup = $attr_r['onkeyup']; } else { $onkeyup = ""; }
      if (isset($attr_r['inputtype'])) { $inputtype = $attr_r['inputtype']; } else { $inputtype = ""; }
      if ($attribute == $LDAP['account_attribute']) { $label = "<strong>$label</strong><sup>&ast;</sup>"; }
      if (isset($$attribute)) { $these_values=$$attribute; } else { $these_values = array(); }
      render_attribute_fields($attribute,$label,$these_values,$dn,$onkeyup,$inputtype);
    }
  ?>

  <div class="row mb-3" id="password_div">
   <label for="password_field" class="col-sm-3 col-form-label text-end">Password</label>
   <div class="col-sm-6">
    <input type="password" class="form-control" id="password_field" name="password" onkeyup="back_to_hidden('password_field','confirm'); check_if_we_should_enable_sending_email();">
   </div>
   <div class="col-sm-3">
    <input type="button" class="btn btn-secondary btn-sm" id="password_generator" onclick="random_password(); check_if_we_should_enable_sending_email();" value="Generate password">
   </div>
  </div>

  <div class="row mb-3" id="confirm_div">
   <label for="confirm" class="col-sm-3 col-form-label text-end">Confirm</label>
   <div class="col-sm-6">
    <input type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
   </div>
  </div>

<?php if ($can_send_email == TRUE) { ?>
  <div class="row mb-3" id="send_email_div">
    <label for="send_email" class="col-sm-3 col-form-label"> </label>
    <div class="col-sm-6">
      <input type="checkbox" class="form-check-input" id="send_email_checkbox" name="send_email" disabled>  Email the updated credentials to the user?
    </div>
  </div>
<?php } ?>


  <div class="row mb-3">
    <p align='center'><button type="submit" class="btn btn-secondary">Update account details</button></p>
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
<?php } else { ?>
<!-- Password Strength Meter (fallback when policy not enabled) -->
<div class="progress">
 <div id="StrengthProgressBar" class="progress-bar"></div>
</div>
<?php } ?>

<div><p align='center'><sup>&ast;</sup>The account identifier.  Changing this will change the full <strong>DN</strong>.</p></div>
