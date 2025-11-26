<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";

if (isset($_POST["admin_password"])) {

 $ldap_connection = open_ldap_connection();
 $user_auth = ldap_setup_auth($ldap_connection,$_POST["admin_password"]);
 ldap_close($ldap_connection);

 if ($user_auth != FALSE) {
  set_setup_cookie($user_auth);
  header("Location: //{$_SERVER["HTTP_HOST"]}{$THIS_MODULE_PATH}/run_checks.php\n\n");
 }
 else {
  header("Location: //{$_SERVER["HTTP_HOST"]}{$THIS_MODULE_PATH}/index.php?invalid\n\n");
 }

}
else {

 render_header("$ORGANISATION_NAME account manager setup - log in");

 if (isset($_GET["invalid"])) {
 ?>
 <div class="container">
  <div class="alert alert-warning">
   <p class="text-center">The password was incorrect.</p>
  </div>
 </div>
 <?php
 }
 ?>
 <div class="container">
  <div class="row justify-content-center">
   <div class="col-md-6 col-lg-4">
    <div class="card">
     <div class="card-header text-center">Password for <?php print $LDAP['admin_bind_dn']; ?></div>
     <div class="card-body">
      <form action='' method='post'>
       <div class="mb-3">
        <input type='password' class="form-control" name='admin_password' placeholder='Password' autofocus>
       </div>
       <div class="d-grid">
        <input type='submit' class="btn btn-secondary" value='Log in'>
       </div>
      </form>
     </div>
    </div>
   </div>
  </div>
 </div>
<?php
}
render_footer();
?>
