<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";

set_page_access("user");

if (isset($_POST['change_password'])) {

 if (!$_POST['password']) { $not_strong_enough = 1; }
 if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $not_strong_enough = 1; }
 if (preg_match("/\"|'/",$_POST['password'])) { $invalid_chars = 1; }
 if ($_POST['password'] != $_POST['password_match']) { $mismatched = 1; }

 if (!isset($mismatched) and !isset($not_strong_enough) and !isset($invalid_chars) ) {

  $ldap_connection = open_ldap_connection();
  ldap_change_password($ldap_connection,$USER_ID,$_POST['password']) or die("change_ldap_password() failed.");

  render_header("$ORGANISATION_NAME account manager - password changed");
  ?>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-sm-6">
        <div class="card border-success">
        <div class="card-header">Success</div>
        <div class="card-body">
          Your password has been updated.
        </div>
      </div>
      </div>
    </div>
  </div>
  <?php
  render_footer();
  exit(0);
 }

}

render_header("Change your $ORGANISATION_NAME password");

if (isset($not_strong_enough)) {  ?>
<div class="alert alert-warning">
 <p class="text-center">The password wasn't strong enough.</p>
</div>
<?php }

if (isset($invalid_chars)) {  ?>
<div class="alert alert-warning">
 <p class="text-center">The password contained invalid characters.</p>
</div>
<?php }

if (isset($mismatched)) {  ?>
<div class="alert alert-warning">
 <p class="text-center">The passwords didn't match.</p>
</div>
<?php }

?>

<script src="<?php print $SERVER_PATH; ?>js/password-utils.js"></script>
<script>
 // Initialize password strength meter
 document.addEventListener('DOMContentLoaded', function() {
   initPasswordStrength('password');
 });
</script>

<div class="container">
 <div class="row justify-content-center">
  <div class="col-sm-6">

   <div class="card">
   <div class="card-header text-center">Change your password</div>

   <ul class="list-group">
    <li class="list-group-item">Use this form to change your <?php print $ORGANISATION_NAME; ?> password.  When you start typing your new password the gauge at the bottom will show its security strength.
    Enter your password again in the <b>confirm</b> field.  If the passwords don't match then both fields will be bordered with red.</li>
   </ul>

   <div class="card-body text-center">
   
    <form class="form-horizontal" action='' method='post'>

     <input type='hidden' id="change_password" name="change_password">
     <input type='hidden' id="pass_score" value="0" name="pass_score">
     
     <div class="row mb-3" id="password_div">
      <label for="password" class="col-sm-4 col-form-label">Password</label>
      <div class="col-sm-6">
       <input type="password" class="form-control" id="password" name="password">
      </div>
     </div>

     <script>
      function check_passwords_match() {
        const password = document.getElementById('password');
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
     </script>

     <div class="row mb-3" id="confirm_div">
      <label for="password" class="col-sm-4 col-form-label">Confirm</label>
      <div class="col-sm-6">
       <input type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
      </div>
     </div>

     <div class="row mb-3">
       <button type="submit" class="btn btn-secondary">Change password</button>
     </div>
     
    </form>

    <div class="progress">
     <div id="StrengthProgressBar" class="progress progress-bar"></div>
    </div>

   </div>
  </div>

  </div>
 </div>
</div>
<?php

render_footer();

?>

