<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$invalid_password = FALSE;
$mismatched_passwords = FALSE;
$invalid_username = FALSE;
$weak_password = FALSE;
$to_update = array();

if ($SMTP['host'] != "") { $can_send_email = TRUE; } else { $can_send_email = FALSE; }

$LDAP['default_attribute_map']["mail"]  = array("label" => "Email", "onkeyup" => "check_if_we_should_enable_sending_email();");

$attribute_map = $LDAP['default_attribute_map'];
if (isset($LDAP['account_additional_attributes'])) { $attribute_map = ldap_complete_attribute_array($attribute_map,$LDAP['account_additional_attributes']); }
if (! array_key_exists($LDAP['account_attribute'], $attribute_map)) {
  $attribute_r = array_merge($attribute_map, array($LDAP['account_attribute'] => array("label" => "Account UID")));
}

if (!isset($_POST['account_identifier']) and !isset($_GET['account_identifier'])) {
?>
 <div class="alert alert-danger">
  <p class="text-center">The account identifier is missing.</p>
 </div>
<?php
render_footer();
exit(0);
}
else {
 $account_identifier = (isset($_POST['account_identifier']) ? $_POST['account_identifier'] : $_GET['account_identifier']);
 $account_identifier = urldecode($account_identifier);
}

$ldap_connection = open_ldap_connection();
$ldap_search_query="({$LDAP['account_attribute']}=". ldap_escape($account_identifier, "", LDAP_ESCAPE_FILTER) . ")";
$ldap_search = ldap_search( $ldap_connection, $LDAP['user_dn'], $ldap_search_query);

// Handle backup code regeneration (admin only)
if (isset($_POST['regenerate_backup_codes'])) {
  if (!$MFA_SCHEMA_OK) {
    render_alert_banner("Cannot regenerate backup codes: TOTP schema is not installed in LDAP.", "danger", 15000);
  } else {
    $regenerate_search = ldap_search($ldap_connection, $LDAP['user_dn'], $ldap_search_query);
    if ($regenerate_search) {
      $regenerate_user = ldap_get_entries($ldap_connection, $regenerate_search);
      if ($regenerate_user['count'] > 0) {
        $regenerate_dn = $regenerate_user[0]['dn'];

        // Generate new backup codes
        $new_backup_codes = totp_generate_backup_codes(10, 8);

        // Update LDAP with new codes
        $modifications = array(
          $TOTP_ATTRS['scratch_codes'] => $new_backup_codes
        );

        if (ldap_mod_replace($ldap_connection, $regenerate_dn, $modifications)) {
          render_alert_banner("New backup codes have been generated. The user should be notified to collect them from their Manage MFA page.");
        } else {
          render_alert_banner("Failed to regenerate backup codes. Check the logs for more information.", "danger", 15000);
        }
      }
    }
  }
}

#########################

if ($ldap_search) {

 $user = ldap_get_entries($ldap_connection, $ldap_search);

 if ($user["count"] > 0) {

  foreach ($attribute_map as $attribute => $attr_r) {

    if (isset($user[0][$attribute]) and $user[0][$attribute]['count'] > 0) {
      $$attribute = $user[0][$attribute];
    }
    else {
      $$attribute = array();
    }

    if (isset($_FILES[$attribute]['size']) and $_FILES[$attribute]['size'] > 0) {

      $this_attribute = array();
      $this_attribute['count'] = 1;
      $this_attribute[0] = file_get_contents($_FILES[$attribute]['tmp_name']);
      $$attribute = $this_attribute;
      $to_update[$attribute] = $this_attribute;
      unset($to_update[$attribute]['count']);

    }

    if (isset($_POST['update_account']) and isset($_POST[$attribute])) {

      $this_attribute = array();

      if (is_array($_POST[$attribute])) {
        foreach($_POST[$attribute] as $key => $value) {
          if ($value != "") { $this_attribute[$key] = trim($value); }
        }
        $this_attribute['count'] = count($this_attribute);
      }
      elseif ($_POST[$attribute] != "") {
        $this_attribute['count'] = 1;
        $this_attribute[0] = trim($_POST[$attribute]);
      }

      if ($this_attribute != $$attribute) {
        $$attribute = $this_attribute;
        $to_update[$attribute] = $this_attribute;
        unset($to_update[$attribute]['count']);
      }

    }

    if (!isset($$attribute) and isset($attr_r['default'])) {
      $$attribute['count'] = 1;
      $$attribute[0] = $attr_r['default'];
    }

  }
  $dn = $user[0]['dn'];

 }
 else {
   ?>
    <div class="alert alert-danger">
     <p class="text-center">This account doesn't exist.</p>
    </div>
   <?php
   render_footer();
   exit(0);
 }

 ### Update values

 if (isset($_POST['update_account'])) {

  // Handle mononym users (only surname) - fixes #213, #171
  $givenname_val = isset($givenname[0]) ? $givenname[0] : '';
  $sn_val = isset($sn[0]) ? $sn[0] : '';

  if (!isset($uid[0])) {
    $uid[0] = generate_username($givenname_val, $sn_val);
    $to_update['uid'] = $uid;
    unset($to_update['uid']['count']);
  }

  if (!isset($cn[0])) {
    if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE) {
      $cn[0] = $givenname_val . $sn_val;
    }
    else {
      $cn[0] = trim($givenname_val . " " . $sn_val);
    }
    $to_update['cn'] = $cn;
    unset($to_update['cn']['count']);
  }

  if (isset($_POST['password']) and $_POST['password'] != "") {

    $password = $_POST['password'];

    if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $weak_password = TRUE; }
    if (preg_match("/\"|'/",$password)) { $invalid_password = TRUE; }
    if ($_POST['password'] != $_POST['password_match']) { $mismatched_passwords = TRUE; }
    if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE and !preg_match("/$USERNAME_REGEX/u",$account_identifier)) { $invalid_username = TRUE; }

    if ( !$mismatched_passwords
       and !$weak_password
       and !$invalid_password
                             ) {
     $to_update['userpassword'][0] = ldap_hashed_password($password);
    }
  }

  if (array_key_exists($LDAP['account_attribute'], $to_update)) {
    $account_attribute = $LDAP['account_attribute'];
    $new_account_identifier = $to_update[$account_attribute][0];
    $new_rdn = "{$account_attribute}={$new_account_identifier}";
    $renamed_entry = ldap_rename($ldap_connection, $dn, $new_rdn, $LDAP['user_dn'], true);
    if ($renamed_entry) {
      $dn = "{$new_rdn},{$LDAP['user_dn']}";
      $account_identifier = $new_account_identifier;
    }
    else {
      ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
      error_log("$log_prefix Failed to rename the DN for {$account_identifier}: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
    }
  }

  $existing_objectclasses = $user[0]['objectclass'];
  unset($existing_objectclasses['count']);
  if ($existing_objectclasses != $LDAP['account_objectclasses']) { $to_update['objectclass'] = $LDAP['account_objectclasses']; }

  $updated_account = @ ldap_mod_replace($ldap_connection, $dn, $to_update);

  if (!$updated_account) {
    ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
    error_log("$log_prefix Failed to modify account details for {$account_identifier}: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
  }

  $sent_email_message="";
  if ($updated_account and isset($mail) and $can_send_email == TRUE and isset($_POST['send_email'])) {

      include_once "mail_functions.inc.php";

      // Handle mononym users for email (fixes #213, #171)
      $givenname_for_mail = isset($givenname[0]) ? $givenname[0] : '';
      $sn_for_mail = isset($sn[0]) ? $sn[0] : '';
      $full_name = trim($givenname_for_mail . " " . $sn_for_mail);

      $mail_body = parse_mail_text($new_account_mail_body, $password, $account_identifier, $givenname_for_mail, $sn_for_mail);
      $mail_subject = parse_mail_text($new_account_mail_subject, $password, $account_identifier, $givenname_for_mail, $sn_for_mail);

      $sent_email = send_email($mail[0], $full_name, $mail_subject, $mail_body);
      if ($sent_email) {
        $sent_email_message .= "  An email sent to {$mail[0]}.";
      }
      else {
        $sent_email_message .= "  Unfortunately the email wasn't sent; check the logs for more information.";
      }
    }

  if ($updated_account) {
    render_alert_banner("The account has been updated.  $sent_email_message");
  }
  else {
    render_alert_banner("There was a problem updating the account.  Check the logs for more information.","danger",15000);
  }
 }


 if ($weak_password) { ?>
 <div class="alert alert-warning">
  <p class="text-center">The password wasn't strong enough.</p>
 </div>
 <?php }

 if ($invalid_password) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">The password contained invalid characters.</p>
 </div>
 <?php }

 if ($mismatched_passwords) {  ?>
 <div class="alert alert-warning">
  <p class="text-center">The passwords didn't match.</p>
 </div>
 <?php }


 ################################################


 $all_groups = ldap_get_group_list($ldap_connection);

 $currently_member_of = ldap_user_group_membership($ldap_connection,$account_identifier);

 $not_member_of = array_diff($all_groups,$currently_member_of);

 #########  Add/remove from groups

 if (isset($_POST["update_member_of"])) {

  $updated_group_membership = array();

  foreach ($_POST as $index => $group) {
   if (is_numeric($index)) {
    array_push($updated_group_membership,$group);
   }
  }

  if ($USER_ID == $account_identifier and !array_search($USER_ID, $updated_group_membership)){
    array_push($updated_group_membership,$LDAP["admins_group"]);
  }

  $groups_to_add = array_diff($updated_group_membership,$currently_member_of);
  $groups_to_del = array_diff($currently_member_of,$updated_group_membership);

  foreach ($groups_to_del as $this_group) {
   ldap_delete_member_from_group($ldap_connection,$this_group,$account_identifier);
  }
  foreach ($groups_to_add as $this_group) {
   ldap_add_member_to_group($ldap_connection,$this_group,$account_identifier);
  }

  $not_member_of = array_diff($all_groups,$updated_group_membership);
  $member_of = $updated_group_membership;
  render_alert_banner("The group membership has been updated.");

 }
 else {
  $member_of = $currently_member_of;
 }

################


?>
<script src="<?php print $SERVER_PATH; ?>js/zxcvbn.min.js"></script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/zxcvbn-bootstrap-strength-meter.js"></script>
<script type="text/javascript">
 $(document).ready(function(){
   $("#StrengthProgressBar").zxcvbnProgressBar({ passwordInput: "#password" });
 });
</script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/generate_passphrase.js"></script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/wordlist.js"></script>
<script>

 function show_delete_user_button() {

  group_del_submit = document.getElementById('delete_user');
  group_del_submit.classList.replace('invisible','visible');


 }

 function check_passwords_match() {

   if (document.getElementById('password').value != document.getElementById('confirm').value ) {
       document.getElementById('password_div').classList.add("has-error");
       document.getElementById('confirm_div').classList.add("has-error");
   }
   else {
    document.getElementById('password_div').classList.remove("has-error");
    document.getElementById('confirm_div').classList.remove("has-error");
   }
  }

 function random_password() {

  generatePassword(4,'-','password','confirm');
  $("#StrengthProgressBar").zxcvbnProgressBar({ passwordInput: "#password" });
 }

 function back_to_hidden(passwordField,confirmField) {

  var passwordField = document.getElementById(passwordField).type = 'password';
  var confirmField = document.getElementById(confirmField).type = 'password';

 }

 function update_form_with_groups() {

  var group_form = document.getElementById('update_with_groups');
  var group_list_ul = document.getElementById('member_of_list');

  var group_list = group_list_ul.getElementsByTagName("li");

  for (var i = 0; i < group_list.length; ++i) {
    var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = i;
        hidden.value = group_list[i]['textContent'];
        group_form.appendChild(hidden);

  }

  group_form.submit();

 }

 $(function () {

    $('body').on('click', '.list-group .list-group-item', function () {
        $(this).toggleClass('active');
    });
    $('.list-arrows button').click(function () {
        var $button = $(this), actives = '';
        if ($button.hasClass('move-left')) {
            actives = $('.list-right ul li.active');
            actives.clone().appendTo('.list-left ul');
            $('.list-left ul li.active').removeClass('active');
            actives.remove();
        } else if ($button.hasClass('move-right')) {
            actives = $('.list-left ul li.active');
            actives.clone().appendTo('.list-right ul');
            $('.list-right ul li.active').removeClass('active');
            actives.remove();
        }
        $("#submit_members").prop("disabled", false);
    });
    $('.dual-list .selector').click(function () {
        var $checkBox = $(this);
        if (!$checkBox.hasClass('selected')) {
            $checkBox.addClass('selected').closest('.well').find('ul li:not(.active)').addClass('active');
            $checkBox.children('i').removeClass('glyphicon-unchecked').addClass('glyphicon-check');
        } else {
            $checkBox.removeClass('selected').closest('.well').find('ul li.active').removeClass('active');
            $checkBox.children('i').removeClass('glyphicon-check').addClass('glyphicon-unchecked');
        }
    });
    $('[name="SearchDualList"]').keyup(function (e) {
        var code = e.keyCode || e.which;
        if (code == '9') return;
        if (code == '27') $(this).val(null);
        var $rows = $(this).closest('.dual-list').find('.list-group li');
        var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();
        $rows.show().filter(function () {
            var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
            return !~text.indexOf(val);
        }).hide();
    });

 });


</script>

<script>

 function check_if_we_should_enable_sending_email() {

  var check_regex = <?php print $JS_EMAIL_REGEX; ?>


  <?php if ($can_send_email == TRUE) { ?>
  if (check_regex.test(document.getElementById("mail").value) && document.getElementById("password").value.length > 0 ) {
    document.getElementById("send_email_checkbox").disabled = false;
  }
  else {
    document.getElementById("send_email_checkbox").disabled = true;
  }

  <?php } ?>
  if (check_regex.test(document.getElementById('mail').value)) {
   document.getElementById("mail_div").classList.remove("has-error");
  }
  else {
   document.getElementById("mail_div").classList.add("has-error");
  }

 }

</script>

<?php render_dynamic_field_js(); ?>

<style type='text/css'>
  .dual-list .list-group {
      margin-top: 8px;
  }

  .list-left li, .list-right li {
      cursor: pointer;
  }

  .list-arrows {
      padding-top: 100px;
  }

  .list-arrows button {
          margin-bottom: 20px;
  }

  .right_button {
    width: 200px;
    float: right;
  }
</style>


<div class="container">
 <div class="col-sm-8 col-md-offset-2">

  <div class="panel panel-default">
    <div class="panel-heading clearfix">
     <span class="panel-title pull-left"><h3><?php print htmlspecialchars(decode_ldap_value($account_identifier), ENT_QUOTES, 'UTF-8'); ?></h3></span>
     <button class="btn btn-warning pull-right align-self-end" style="margin-top: auto;" onclick="show_delete_user_button();" <?php if ($account_identifier == $USER_ID) { print "disabled"; }?>>Delete account</button>
     <form action="<?php print "{$THIS_MODULE_PATH}"; ?>/index.php" method="post"><input type="hidden" name="delete_user" value="<?php print urlencode($account_identifier); ?>"><button class="btn btn-danger pull-right invisible" id="delete_user">Confirm deletion</button></form>
    </div>
    <ul class="list-group">
      <li class="list-group-item"><?php print htmlspecialchars(decode_ldap_value($dn), ENT_QUOTES, 'UTF-8'); ?></li>
    </li>
    <div class="panel-body">
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

      <div class="form-group" id="password_div">
       <label for="password" class="col-sm-3 control-label">Password</label>
       <div class="col-sm-6">
        <input type="password" class="form-control" id="password" name="password" onkeyup="back_to_hidden('password','confirm'); check_if_we_should_enable_sending_email();">
       </div>
       <div class="col-sm-1">
        <input type="button" class="btn btn-sm" id="password_generator" onclick="random_password(); check_if_we_should_enable_sending_email();" value="Generate password">
       </div>
      </div>

      <div class="form-group" id="confirm_div">
       <label for="confirm" class="col-sm-3 control-label">Confirm</label>
       <div class="col-sm-6">
        <input type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
       </div>
      </div>

<?php if ($can_send_email == TRUE) { ?>
      <div class="form-group" id="send_email_div">
        <label for="send_email" class="col-sm-3 control-label"> </label>
        <div class="col-sm-6">
          <input type="checkbox" class="form-check-input" id="send_email_checkbox" name="send_email" disabled>  Email the updated credentials to the user?
        </div>
      </div>
<?php } ?>


      <div class="form-group">
        <p align='center'><button type="submit" class="btn btn-default">Update account details</button></p>
      </div>

    </form>

    <div class="progress">
     <div id="StrengthProgressBar" class="progress-bar"></div>
    </div>

    <div><p align='center'><sup>&ast;</sup>The account identifier.  Changing this will change the full <strong>DN</strong>.</p></div>

   </div>
  </div>

 </div>
</div>

<?php if ($MFA_ENABLED == TRUE) {
  // Get MFA status for this user
  $user_totp_status = isset($user[0]['totpstatus'][0]) ? $user[0]['totpstatus'][0] : 'none';
  $user_backup_code_count = totp_get_backup_code_count($ldap_connection, $dn);
  $user_requires_mfa = totp_user_requires_mfa($ldap_connection, $account_identifier, $MFA_REQUIRED_GROUPS);
?>
<div class="container">
 <div class="col-sm-8 col-md-offset-2">
  <div class="panel panel-default">
   <div class="panel-heading clearfix">
    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">Multi-Factor Authentication</h3>
   </div>
   <div class="panel-body">
    <table class="table table-condensed">
      <tr>
        <th width="30%">MFA Status:</th>
        <td>
          <?php
            switch ($user_totp_status) {
              case 'active':
                echo '<span class="label label-success">Active</span>';
                break;
              case 'pending':
                echo '<span class="label label-warning">Pending Setup</span>';
                break;
              case 'disabled':
                echo '<span class="label label-default">Disabled</span>';
                break;
              default:
                echo '<span class="label label-default">Not Configured</span>';
            }
          ?>
        </td>
      </tr>
      <?php if ($user_requires_mfa) { ?>
      <tr>
        <th>MFA Required:</th>
        <td><span class="label label-info">Yes</span> (Required by group membership)</td>
      </tr>
      <?php } ?>
      <?php if ($user_totp_status == 'active' && $user_backup_code_count > 0) { ?>
      <tr>
        <th>Backup Codes:</th>
        <td>
          <span class="label <?php echo $user_backup_code_count < 3 ? 'label-warning' : 'label-info'; ?>">
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

   </div>
  </div>
 </div>
</div>
<?php } ?>

<div class="container">
 <div class="col-sm-12">

  <div class="panel panel-default">
   <div class="panel-heading clearfix">
    <h3 class="panel-title pull-left" style="padding-top: 7.5px;">Group membership</h3>
   </div>
   <div class="panel-body">

    <div class="row">

         <div class="dual-list list-left col-md-5">
          <strong>Member of</strong>
          <div class="well">
           <div class="row">
            <div class="col-md-10">
             <div class="input-group">
              <span class="input-group-addon glyphicon glyphicon-search"></span>
              <input type="text" name="SearchDualList" class="form-control" placeholder="search" />
             </div>
            </div>
            <div class="col-md-2">
             <div class="btn-group">
              <a class="btn btn-default selector" title="select all"><i class="glyphicon glyphicon-unchecked"></i></a>
             </div>
            </div>
           </div>
           <ul class="list-group" id="member_of_list">
            <?php
            foreach ($member_of as $group) {
              $group_display = htmlspecialchars(decode_ldap_value($group), ENT_QUOTES, 'UTF-8');
              if ($group == $LDAP["admins_group"] and $USER_ID == $account_identifier) {
                print "<div class='list-group-item' style='opacity: 0.5; pointer-events:none;'>{$group_display}</div>\n";
              }
              else {
                print "<li class='list-group-item'>{$group_display}</li>\n";
              }
            }
            ?>
           </ul>
          </div>
         </div>

         <div class="list-arrows col-md-1 text-center">
          <button class="btn btn-default btn-sm move-left">
           <span class="glyphicon glyphicon-chevron-left"></span>
          </button>
          <button class="btn btn-default btn-sm move-right">
           <span class="glyphicon glyphicon-chevron-right"></span>
          </button>
          <form id="update_with_groups" action="<?php print $CURRENT_PAGE ?>" method="post">
           <input type="hidden" name="update_member_of">
           <input type="hidden" name="account_identifier" value="<?php print $account_identifier; ?>">
          </form>
          <button id="submit_members" class="btn btn-info" disabled type="submit" onclick="update_form_with_groups()">Save</button>
         </div>

         <div class="dual-list list-right col-md-5">
          <strong>Available groups</strong>
          <div class="well">
           <div class="row">
            <div class="col-md-2">
             <div class="btn-group">
              <a class="btn btn-default selector" title="select all"><i class="glyphicon glyphicon-unchecked"></i></a>
             </div>
            </div>
            <div class="col-md-10">
             <div class="input-group">
              <input type="text" name="SearchDualList" class="form-control" placeholder="search" />
              <span class="input-group-addon glyphicon glyphicon-search"></span>
             </div>
            </div>
           </div>
           <ul class="list-group">
            <?php
             foreach ($not_member_of as $group) {
               $group_display = htmlspecialchars(decode_ldap_value($group), ENT_QUOTES, 'UTF-8');
               print "<li class='list-group-item'>{$group_display}</li>\n";
             }
            ?>
           </ul>
          </div>
         </div>

   </div>
	</div>
</div>


<?php

}

render_footer();

?>
