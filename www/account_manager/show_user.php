<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "password_policy_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

// Define constant to allow includes
define('LDAP_USER_MANAGER', true);

// Include tab configuration
include_once __DIR__ . '/includes/user_tab_config.php';

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
 <div class="container">
  <div class="alert alert-danger">
   <p class="text-center">The account identifier is missing.</p>
  </div>
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

  // Get tab configuration
  $user_tabs = get_user_tabs_config('show_user');

  // Include all tab handlers directly (in main scope for variable access)
  foreach ($user_tabs as $tab) {
    if (!empty($tab['handler_file'])) {
      $handler_path = __DIR__ . '/includes/' . $tab['handler_file'];
      if (file_exists($handler_path)) {
        include_once $handler_path;
      }
    }
  }

 }
 else {
   ?>
    <div class="container">
     <div class="alert alert-danger">
      <p class="text-center">This account doesn't exist.</p>
     </div>
    </div>
   <?php
   render_footer();
   exit(0);
 }

?>
<div class="container">
<?php

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

 if (isset($password_fails_policy) && $password_fails_policy && !empty($password_policy_errors)) { ?>
  <div class="alert alert-warning">
   <p class="text-center"><strong>Password Policy Errors:</strong></p>
   <ul>
    <?php foreach ($password_policy_errors as $policy_error) { ?>
      <li><?php echo htmlspecialchars($policy_error); ?></li>
    <?php } ?>
   </ul>
  </div>
 <?php }

 if (isset($password_in_history) && $password_in_history) { ?>
  <div class="alert alert-warning">
   <p class="text-center">This password was used recently and cannot be reused.</p>
  </div>
 <?php }

?>
</div>
<?php


 ################################################

// Group membership handler is now loaded via tab configuration system (see lines 121-128)

################


?>
<script src="<?php print url('/js/password-utils.js'); ?>"></script>
<script>

 // Initialise password requirements checker or strength meter
 document.addEventListener('DOMContentLoaded', function() {
   <?php if ($PASSWORD_POLICY_ENABLED) { ?>
   window.passwordRequirements = {
     minLength: <?php echo (int)$PASSWORD_MIN_LENGTH; ?>,
     requireUppercase: <?php echo $PASSWORD_REQUIRE_UPPERCASE ? 'true' : 'false'; ?>,
     requireLowercase: <?php echo $PASSWORD_REQUIRE_LOWERCASE ? 'true' : 'false'; ?>,
     requireNumbers: <?php echo $PASSWORD_REQUIRE_NUMBERS ? 'true' : 'false'; ?>,
     requireSpecial: <?php echo $PASSWORD_REQUIRE_SPECIAL ? 'true' : 'false'; ?>
   };
   initPasswordRequirements('password_field', window.passwordRequirements);
   <?php } else { ?>
   initPasswordStrength('password_field');
   <?php } ?>
 });

 function show_delete_user_button() {
  const group_del_submit = document.getElementById('delete_user');
  group_del_submit.classList.replace('invisible','visible');
 }

 function check_passwords_match() {
   const password = document.getElementById('password_field');
   const confirm = document.getElementById('confirm');

   if (password.value != confirm.value) {
       password.classList.add("is-invalid");
       confirm.classList.add("is-invalid");
   }
   else {
    password.classList.remove("is-invalid");
    confirm.classList.remove("is-invalid");
   }
  }

 function random_password() {
  generatePassword(4,'-','password_field','confirm');
  check_if_we_should_enable_sending_email();
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

 document.addEventListener('DOMContentLoaded', function() {

    // Click handler for list items to toggle active state
    document.body.addEventListener('click', function(e) {
        const listItem = e.target.closest('.list-group .list-group-item');
        if (listItem) {
            listItem.classList.toggle('active');
        }
    });

    // Arrow button handlers to move items between lists
    document.querySelectorAll('.list-arrows button').forEach(function(button) {
        button.addEventListener('click', function() {
            if (this.classList.contains('move-left')) {
                const actives = document.querySelectorAll('.list-right ul li.active');
                const leftUl = document.querySelector('.list-left ul');
                actives.forEach(function(item) {
                    const clone = item.cloneNode(true);
                    leftUl.appendChild(clone);
                    clone.classList.remove('active');
                    item.remove();
                });
            } else if (this.classList.contains('move-right')) {
                const actives = document.querySelectorAll('.list-left ul li.active');
                const rightUl = document.querySelector('.list-right ul');
                actives.forEach(function(item) {
                    const clone = item.cloneNode(true);
                    rightUl.appendChild(clone);
                    clone.classList.remove('active');
                    item.remove();
                });
            }
            document.getElementById('submit_members').disabled = false;
        });
    });

    // Select all checkbox handlers
    document.querySelectorAll('.dual-list .selector').forEach(function(selector) {
        selector.addEventListener('change', function() {
            const well = this.closest('.well');
            if (this.checked) {
                well.querySelectorAll('ul li:not(.active)').forEach(function(li) {
                    li.classList.add('active');
                });
            } else {
                well.querySelectorAll('ul li.active').forEach(function(li) {
                    li.classList.remove('active');
                });
            }
        });
    });

    // Search functionality
    document.querySelectorAll('[name="SearchDualList"]').forEach(function(input) {
        input.addEventListener('keyup', function(e) {
            const code = e.keyCode || e.which;
            if (code == '9') return;
            if (code == '27') this.value = '';
            const dualList = this.closest('.dual-list');
            const rows = dualList.querySelectorAll('.list-group li');
            const val = this.value.trim().replace(/ +/g, ' ').toLowerCase();
            rows.forEach(function(row) {
                const text = row.textContent.replace(/\s+/g, ' ').toLowerCase();
                if (text.indexOf(val) === -1) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
        });
    });

 });


</script>

<script>

 function check_if_we_should_enable_sending_email() {

  var check_regex = <?php print $JS_EMAIL_REGEX; ?>


  <?php if ($can_send_email == TRUE) { ?>
  if (check_regex.test(document.getElementById("mail").value) && document.getElementById("password_field").value.length > 0 ) {
    document.getElementById("send_email_checkbox").disabled = false;
  }
  else {
    document.getElementById("send_email_checkbox").disabled = true;
  }

  <?php } ?>
  if (check_regex.test(document.getElementById('mail').value)) {
   document.getElementById("mail").classList.remove("is-invalid");
   document.getElementById("mail").classList.add("is-valid");
  }
  else {
   document.getElementById("mail").classList.add("is-invalid");
   document.getElementById("mail").classList.remove("is-valid");
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

  .select-all-wrapper {
      margin-bottom: 8px;
      padding: 6px 0;
  }

  .select-all-wrapper .form-check-input {
      cursor: pointer;
  }

  .select-all-wrapper .form-check-label {
      cursor: pointer;
      margin-left: 4px;
  }

  /* Remove extra spacing from tab content */
  .tab-content {
    padding-top: 0 !important;
    margin-top: 0 !important;
  }

  .tab-content .tab-pane {
    padding-top: 0 !important;
    margin-top: 0 !important;
  }
</style>


<div class="container">
 <div class="col-sm-12">

  <!-- Page Header -->
  <div class="row mb-3">
    <div class="col-md-8">
      <h2><?php print htmlspecialchars(decode_ldap_value($account_identifier), ENT_QUOTES, 'UTF-8'); ?></h2>
      <p class="text-muted"><?php print htmlspecialchars(decode_ldap_value($dn), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="col-md-4 text-end">
      <button class="btn btn-warning" onclick="show_delete_user_button();" <?php if ($account_identifier == $USER_ID) { print "disabled"; }?>>Delete account</button>
      <form action="<?php print "{$THIS_MODULE_PATH}"; ?>/index.php" method="post" style="display: inline;">
        <input type="hidden" name="delete_user" value="<?php print urlencode($account_identifier); ?>">
        <button class="btn btn-danger invisible" id="delete_user">Confirm deletion</button>
      </form>
    </div>
  </div>

  <!-- Tab Navigation -->
  <?php render_tab_navigation($user_tabs, 'userTabs'); ?>

  <!-- Tab Content -->
  <div class="tab-content" id="userTabContent">
  <?php
    foreach ($user_tabs as $tab) {
      $active_class = $tab['active'] ? ' show active' : '';
      echo '  <!-- ' . htmlspecialchars($tab['label']) . ' Tab -->' . "\n";
      echo '  <div class="tab-pane fade' . $active_class . '" ';
      echo 'id="' . $tab['id'] . '" ';
      echo 'role="tabpanel" ';
      echo 'aria-labelledby="' . $tab['id'] . '-tab">' . "\n";
      echo '    <div class="card border-top-0">' . "\n";
      echo '    <div class="card-body">' . "\n";

      // Include the tab content file (in main scope for variable access)
      $tab_file_path = __DIR__ . '/includes/' . $tab['tab_file'];
      if (file_exists($tab_file_path)) {
        include $tab_file_path;
      } else {
        echo '      <div class="alert alert-warning">' . "\n";
        echo '        Tab content not yet implemented: ' . htmlspecialchars($tab['label']) . "\n";
        echo '      </div>' . "\n";
      }

      echo '    </div>' . "\n";
      echo '    </div>' . "\n";
      echo '  </div>' . "\n";
      echo '  <!-- End ' . htmlspecialchars($tab['label']) . ' Tab -->' . "\n";
      echo "\n";
    }
  ?>
  </div>
  <!-- End Tab Content -->

 </div>
</div>

<?php render_tab_persistence_js('show_user_active_tab', '#userTabs'); ?>

<?php
}

render_footer();

?>
