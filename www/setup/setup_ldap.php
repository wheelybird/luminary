<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "ldap_app_data_functions.inc.php";
include_once "module_functions.inc.php";

validate_setup_cookie();
set_page_access("setup");

render_header("$ORGANISATION_NAME account manager setup");

$ldap_connection = open_ldap_connection();

$no_errors = TRUE;
$show_create_admin_button = FALSE;

# Set up missing stuff

if (isset($_POST['fix_problems'])) {
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
      var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
      var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
      });
    });
</script>
<div class='container'>

 <div class="card">
  <div class="card-header">Updating LDAP...</div>
   <div class="card-body">
    <ul class="list-group">

<?php

 if (isset($_POST['setup_group_ou'])) {
  $ou_add = @ ldap_add($ldap_connection, $LDAP['group_dn'], array( 'objectClass' => 'organizationalUnit', 'ou' => $LDAP['group_ou'] ));
  if ($ou_add == TRUE) {
   print "$li_good Created OU <strong>{$LDAP['group_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create {$LDAP['group_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_user_ou'])) {
  $ou_add = @ ldap_add($ldap_connection, $LDAP['user_dn'], array( 'objectClass' => 'organizationalUnit', 'ou' => $LDAP['user_ou'] ));
  if ($ou_add == TRUE) {
   print "$li_good Created OU <strong>{$LDAP['user_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create {$LDAP['user_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_last_gid'])) {

  $highest_gid = ldap_get_highest_id($ldap_connection,'gid');
  $description = "Records the last GID used to create a Posix group. This prevents the re-use of a GID from a deleted group.";

  $add_lastgid_r = array( 'objectClass' => array('device','top'),
                          'serialnumber' => $highest_gid,
                          'description' => $description );

  $gid_add = @ ldap_add($ldap_connection, "cn=lastGID,{$LDAP['base_dn']}", $add_lastgid_r);

  if ($gid_add == TRUE) {
   print "$li_good Created <strong>cn=lastGID,{$LDAP['base_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create cn=lastGID,{$LDAP['base_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_last_uid'])) {

  $highest_uid = ldap_get_highest_id($ldap_connection,'uid');
  $description = "Records the last UID used to create a Posix account. This prevents the re-use of a UID from a deleted account.";

  $add_lastuid_r = array( 'objectClass' => array('device','top'),
                          'serialnumber' => $highest_uid,
                          'description' => $description );

  $uid_add = @ ldap_add($ldap_connection, "cn=lastUID,{$LDAP['base_dn']}", $add_lastuid_r);

  if ($uid_add == TRUE) {
   print "$li_good Created <strong>cn=lastUID,{$LDAP['base_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create cn=lastUID,{$LDAP['base_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_default_group'])) {

  $group_add = ldap_new_group($ldap_connection,$DEFAULT_USER_GROUP);
  
  if ($group_add == TRUE) {
   print "$li_good Created default group: <strong>$DEFAULT_USER_GROUP</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create default group: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }

 if (isset($_POST['setup_admins_group'])) {

  $group_add = ldap_new_group($ldap_connection,$LDAP['admins_group']);

  if ($group_add == TRUE) {
   print "$li_good Created LDAP administrators group: <strong>{$LDAP['admins_group']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create LDAP administrators group: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }

 if (isset($_POST['setup_apps_ou']) || isset($_POST['setup_luminary_entry'])) {
  // Use the shared function to create both ou=applications and cn=luminary
  $ldap_storage_created = ldap_app_data_create_entry($ldap_connection);

  if ($ldap_storage_created == TRUE) {
   if (isset($_POST['setup_apps_ou'])) {
    print "$li_good Created OU <strong>ou=applications,{$LDAP['base_dn']}</strong></li>\n";
   }
   if (isset($_POST['setup_luminary_entry'])) {
    print "$li_good Created <strong>cn=luminary,ou=applications,{$LDAP['base_dn']}</strong></li>\n";
   }
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create LDAP storage entries: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }

 $admins = ldap_get_group_members($ldap_connection,$LDAP['admins_group']);

 if (count($admins) < 1) {

  ?>
  <div class="row mb-3">
  <form action="<?php print "{$SERVER_PATH}account_manager/new_user.php"; ?>" method="post">
  <input type="hidden" name="setup_admin_account">
  <?php
  print "$li_fail The LDAP administration group is empty. ";
  print "<a href='#' data-bs-toggle='popover' data-bs-trigger='hover focus' title='LDAP account administrators' data-bs-content='";
  print "Only members of this group ({$LDAP['admins_group']}) will be able to access the account managment section, so we need to add people to it.";
  print "'>What's this?</a>";
  print "<label class='float-end'><input type='checkbox' name='setup_admin_account' class='float-end' checked>Create a new account and add it to the admin group?&nbsp;</label>";
  print "</li>\n";
  $show_create_admin_button = TRUE;
 }
 else {
  print "$li_good The LDAP account administrators group (<strong>{$LDAP['admins_group']}</strong>) isn't empty.</li>";
 }


?>
  </ul>
 </div>
</div>
<?php

##############

 if ($no_errors == TRUE) {
  if ($show_create_admin_button == FALSE) {
 ?>
 </form>
 <div class='mt-3 text-end'>
  <form action="<?php print $THIS_MODULE_PATH; ?>" class="d-inline">
   <input type='submit' class="btn btn-success" value='Finished'>
  </form>
 </div>
 <?php
  }
  else {
  ?>
    <div class='mt-3 text-end'>
    <input type='submit' class="btn btn-warning" value='Create new account >'>
   </form>
  </div>
  <?php
  }
 }
 else {
 ?>
 </form>
 <div class='mt-3 text-start'>
  <form action="<?php print $THIS_MODULE_PATH; ?>/run_checks.php" class="d-inline">
   <input type='submit' class="btn btn-danger" value='< Re-run setup'>
  </form>
 </div>
<?php

 }

}

render_footer();

?>
