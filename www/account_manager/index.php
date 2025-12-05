<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "module_functions.inc.php";
include_once "ldap_app_data_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$ldap_connection = open_ldap_connection();

// Handle LDAP storage entry creation
if (isset($_POST['create_ldap_storage'])) {
  $created = ldap_app_data_create_entry($ldap_connection);

  if ($created) {
    audit_log('ldap_storage_created', 'system', 'LDAP application data entry created', 'success', $USER_ID);
    render_alert_banner("LDAP storage entry created successfully. Persistent data storage is now available.");
  }
  else {
    audit_log('ldap_storage_create_failure', 'system', 'Failed to create LDAP application data entry', 'failure', $USER_ID);
    render_alert_banner("Failed to create LDAP storage entry. Check the logs for more information.","danger",15000);
  }
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = $PAGINATION_ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Get search filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if (isset($_POST['delete_user'])) {

  $this_user = $_POST['delete_user'];
  $this_user = urldecode($this_user);

  $del_user = ldap_delete_account($ldap_connection,$this_user);

  if ($del_user) {
    // Audit log user deletion
    audit_log('user_deleted', $this_user, "User account deleted by admin", 'success', $USER_ID);
    render_alert_banner("User <strong>$this_user</strong> was deleted.");
  }
  else {
    // Audit log failed deletion
    audit_log('user_delete_failure', $this_user, "Failed to delete user account", 'failure', $USER_ID);
    render_alert_banner("User <strong>$this_user</strong> wasn't deleted.  See the logs for more information.","danger",15000);
  }


}

// Get all users matching filter (for total count)
$all_people = ldap_get_user_list($ldap_connection);

// Apply search filter if provided
if (!empty($filter)) {
  $all_people = array_filter($all_people, function($attribs, $account) use ($filter) {
    $search_string = strtolower($account . ' ' .
                                 ($attribs['givenname'] ?? '') . ' ' .
                                 ($attribs['sn'] ?? '') . ' ' .
                                 ($attribs['mail'] ?? ''));
    return strpos($search_string, strtolower($filter)) !== false;
  }, ARRAY_FILTER_USE_BOTH);
}

// Calculate pagination
$total_users = count($all_people);
$total_pages = ceil($total_users / $per_page);

// Get paginated subset
$people = array_slice($all_people, $offset, $per_page, true);

?>
<div class="container">
 <div class="row mb-3">
   <div class="col-md-6">
     <form action="<?php print $THIS_MODULE_PATH; ?>/new_user.php" method="post" class="d-inline">
       <button type="button" class="btn btn-light"><?php print number_format($total_users);?> account<?php if ($total_users != 1) { print "s"; }?></button>
       <button id="add_group" class="btn btn-secondary" type="submit">New user</button>
     </form>
   </div>
   <div class="col-md-6">
     <form action="" method="get" class="d-flex">
       <input class="form-control me-2" id="search_input" name="filter" type="text" placeholder="Search users..." value="<?php echo htmlspecialchars($filter); ?>">
       <button type="submit" class="btn btn-primary">Search</button>
       <?php if (!empty($filter)) { ?>
         <a href="?" class="btn btn-secondary ms-2">Clear</a>
       <?php } ?>
     </form>
   </div>
 </div>

 <?php if (!empty($filter)) { ?>
   <div class="alert alert-info">
     Showing <?php echo count($people); ?> of <?php echo number_format($total_users); ?> users matching "<?php echo htmlspecialchars($filter); ?>"
   </div>
 <?php } ?>

 <?php
 // LDAP Storage Status - only show when there's an issue that needs admin attention
 if (($USE_LDAP_AS_DB == TRUE || $PASSWORD_RESET_ENABLED == TRUE)) {
   $ldap_storage_enabled = ($USE_LDAP_AS_DB == TRUE);
   $ldap_entry_exists = ldap_app_data_entry_exists($ldap_connection);

   // Only show card if LDAP is enabled but entry doesn't exist (needs action)
   if ($ldap_storage_enabled && !$ldap_entry_exists) {
 ?>
 <div class="card mb-3 border-danger">
   <div class="card-header bg-danger text-white">
     <strong>⚠ Action Required: LDAP Storage Entry Missing</strong>
   </div>
   <div class="card-body">
     <p><strong>LDAP persistent storage is enabled but the required entry doesn't exist.</strong></p>
     <p>Sessions, password reset tokens, and other persistent data cannot be stored in LDAP until you create the storage entry.</p>
     <p class="mb-3"><small class="text-muted">Currently using /tmp storage - data will be lost on container restart.</small></p>
     <form method="post" class="d-inline">
       <button type="submit" name="create_ldap_storage" class="btn btn-primary">
         Create LDAP Storage Entry
       </button>
       <small class="text-muted ms-2">This will create cn=luminary,ou=applications in your LDAP directory</small>
     </form>
   </div>
 </div>
 <?php
   }
 }
 ?>

 <table class="table table-striped">
  <thead>
   <tr>
     <th>Account name</th>
     <th>First name</th>
     <th>Last name</th>
     <th>Email</th>
   </tr>
  </thead>
 <tbody id="userlist">
<?php
foreach ($people as $account_identifier => $attribs){
  $this_mail = isset($people[$account_identifier]['mail']) ? $people[$account_identifier]['mail'] : "";
  $this_givenname = isset($people[$account_identifier]['givenname']) ? $people[$account_identifier]['givenname'] : "";
  $this_sn = isset($people[$account_identifier]['sn']) ? $people[$account_identifier]['sn'] : "";

  print " <tr>\n   <td><a href='{$THIS_MODULE_PATH}/show_user.php?account_identifier=" . urlencode($account_identifier) . "'>$account_identifier</a></td>\n";
  print "   <td>" . htmlspecialchars($this_givenname) . "</td>\n";
  print "   <td>" . htmlspecialchars($this_sn) . "</td>\n";
  print "   <td>" . htmlspecialchars($this_mail) . "</td>\n";
  print " </tr>\n";
}

if (count($people) == 0) {
  print " <tr><td colspan='4' class='text-center text-muted'>No users found</td></tr>\n";
}
?>
  </tbody>
 </table>

 <!-- Pagination -->
 <?php if ($total_pages > 1) { ?>
   <nav aria-label="User list pagination" class="mt-3">
     <ul class="pagination justify-content-center">
       <!-- Previous -->
       <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
         <a class="page-link" href="?page=<?php echo $page - 1; ?><?php if (!empty($filter)) echo '&filter=' . urlencode($filter); ?>">Previous</a>
       </li>

       <!-- Page numbers -->
       <?php
       $start_page = max(1, $page - 5);
       $end_page = min($total_pages, $page + 5);

       if ($start_page > 1) {
         echo '<li class="page-item"><a class="page-link" href="?page=1';
         if (!empty($filter)) echo '&filter=' . urlencode($filter);
         echo '">1</a></li>';
         if ($start_page > 2) {
           echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
         }
       }

       for ($i = $start_page; $i <= $end_page; $i++) {
         $active = ($i == $page) ? 'active' : '';
         echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i;
         if (!empty($filter)) echo '&filter=' . urlencode($filter);
         echo '">' . $i . '</a></li>';
       }

       if ($end_page < $total_pages) {
         if ($end_page < $total_pages - 1) {
           echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
         }
         echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages;
         if (!empty($filter)) echo '&filter=' . urlencode($filter);
         echo '">' . $total_pages . '</a></li>';
       }
       ?>

       <!-- Next -->
       <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
         <a class="page-link" href="?page=<?php echo $page + 1; ?><?php if (!empty($filter)) echo '&filter=' . urlencode($filter); ?>">Next</a>
       </li>
     </ul>
   </nav>

   <p class="text-center text-muted">
     Page <?php echo $page; ?> of <?php echo number_format($total_pages); ?>
     (Showing <?php echo (($page - 1) * $per_page) + 1; ?>-<?php echo min($page * $per_page, $total_users); ?> of <?php echo number_format($total_users); ?>)
   </p>
 <?php } ?>
</div>
<?php

ldap_close($ldap_connection);
render_footer();
?>
