<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$ldap_connection = open_ldap_connection();

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = $PAGINATION_ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Get search filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if (isset($_POST['delete_group'])) {

 $this_group = $_POST['delete_group'];
 $this_group = urldecode($this_group);

 $del_group = ldap_delete_group($ldap_connection,$this_group);

 if ($del_group) {
   // Audit log group deletion
   audit_log('group_deleted', $this_group, "Group deleted by admin", 'success', $USER_ID);
   render_alert_banner("Group <strong>$this_group</strong> was deleted.");
 }
 else {
   // Audit log failed deletion
   audit_log('group_delete_failure', $this_group, "Failed to delete group", 'failure', $USER_ID);
   render_alert_banner("Group <strong>$this_group</strong> wasn't deleted.  See the logs for more information.","danger",15000);
 }

}

// Get all groups
$all_groups = ldap_get_group_list($ldap_connection);

// Apply search filter if provided (fetch details only for matching groups)
$filtered_groups = $all_groups;
if (!empty($filter)) {
  $filtered_groups = array();
  foreach ($all_groups as $group_cn) {
    $group_entry = ldap_get_group_entry($ldap_connection, $group_cn);
    if ($group_entry) {
      $description = isset($group_entry[0]['description'][0]) ? $group_entry[0]['description'][0] : '';
      $search_string = strtolower($group_cn . ' ' . $description);
      if (strpos($search_string, strtolower($filter)) !== false) {
        $filtered_groups[] = $group_cn;
      }
    }
  }
}

// Calculate pagination
$total_groups = count($filtered_groups);
$total_pages = ceil($total_groups / $per_page);

// Get paginated subset
$groups = array_slice($filtered_groups, $offset, $per_page);

// Fetch group details (description, MFA status) for paginated groups only
$group_details = array();
foreach ($groups as $group_cn) {
  $group_entry = ldap_get_group_entry($ldap_connection, $group_cn);
  if ($group_entry) {
    $details = array(
      'description' => isset($group_entry[0]['description'][0]) ? $group_entry[0]['description'][0] : '',
      'mfa_required' => false,
      'mfa_grace_period' => null
    );

    // Get MFA status if MFA is enabled
    if ($MFA_FEATURE_ENABLED == TRUE) {
      $mfa_required_attr = strtolower($GROUP_MFA_ATTRS['required']);
      $mfa_grace_period_attr = strtolower($GROUP_MFA_ATTRS['grace_period']);

      if (isset($group_entry[0][$mfa_required_attr][0])) {
        $details['mfa_required'] = (strcasecmp($group_entry[0][$mfa_required_attr][0], 'TRUE') == 0);
      }
      if (isset($group_entry[0][$mfa_grace_period_attr][0])) {
        $details['mfa_grace_period'] = intval($group_entry[0][$mfa_grace_period_attr][0]);
      }
    }

    $group_details[$group_cn] = $details;
  }
}

ldap_close($ldap_connection);

render_js_username_check();

?>
<script type="text/javascript">

 function show_new_group_form() {

  group_form = document.getElementById('group_name');
  group_submit = document.getElementById('add_group');
  group_form.classList.replace('invisible','visible');
  group_submit.classList.replace('invisible','visible');


 }

</script>
<div class="container">
 <div class="row mb-3">
   <div class="col-md-6">
     <form action="<?php print "{$THIS_MODULE_PATH}"; ?>/show_group.php" method="post" class="d-inline">
       <input type="hidden" name="new_group">
       <button type="button" class="btn btn-light"><?php print number_format($total_groups);?> group<?php if ($total_groups != 1) { print "s"; }?></button>
       <button id="show_new_group" class="btn btn-secondary" type="button" onclick="show_new_group_form();">New group</button>
       <input type="text" class="invisible" name="group_name" id="group_name" placeholder="Group name" onkeyup="check_entity_name_validity(document.getElementById('group_name').value,'new_group_div');">
       <button id="add_group" class="btn btn-primary invisible" type="submit">Add</button>
     </form>
   </div>
   <div class="col-md-6">
     <form action="" method="get" class="d-flex">
       <input class="form-control me-2" id="search_input" name="filter" type="text" placeholder="Search groups..." value="<?php echo htmlspecialchars($filter); ?>">
       <button type="submit" class="btn btn-primary">Search</button>
       <?php if (!empty($filter)) { ?>
         <a href="?" class="btn btn-secondary ms-2">Clear</a>
       <?php } ?>
     </form>
   </div>
 </div>

 <?php if (!empty($filter)) { ?>
   <div class="alert alert-info">
     Showing <?php echo count($groups); ?> of <?php echo number_format($total_groups); ?> groups matching "<?php echo htmlspecialchars($filter); ?>"
   </div>
 <?php } ?>

 <table class="table table-striped">
  <thead>
   <tr>
     <th style="width: 25%;">Group name</th>
     <th>Description</th>
     <?php if ($MFA_FEATURE_ENABLED == TRUE) { ?>
     <th style="width: 1%;" class="text-nowrap"><i class="bi bi-shield-lock"></i> MFA</th>
     <?php } ?>
   </tr>
  </thead>
 <tbody id="grouplist">
<?php
foreach ($groups as $group) {
  $details = isset($group_details[$group]) ? $group_details[$group] : array('description' => '', 'mfa_required' => false);

  print " <tr>\n";
  print "   <td><a href='{$THIS_MODULE_PATH}/show_group.php?group_name=" . urlencode($group) . "'>$group</a></td>\n";

  // Description column
  $description = !empty($details['description']) ? htmlspecialchars(decode_ldap_value($details['description']), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>';
  print "   <td>$description</td>\n";

  // MFA column (only if MFA is enabled)
  if ($MFA_FEATURE_ENABLED == TRUE) {
    if ($details['mfa_required']) {
      $grace = $details['mfa_grace_period'] !== null ? $details['mfa_grace_period'] . 'd' : '?';
      print "   <td class=\"text-nowrap\"><span class=\"badge bg-success\" title=\"Grace period: {$grace}\">Required</span></td>\n";
    } else {
      print "   <td class=\"text-nowrap\"><span class=\"badge bg-secondary\">—</span></td>\n";
    }
  }

  print " </tr>\n";
}

if (count($groups) == 0) {
  $colspan = $MFA_FEATURE_ENABLED == TRUE ? '3' : '2';
  print " <tr><td colspan='$colspan' class='text-center text-muted'>No groups found</td></tr>\n";
}
?>
  </tbody>
 </table>

 <!-- Pagination -->
 <?php if ($total_pages > 1) { ?>
   <nav aria-label="Group list pagination" class="mt-3">
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
     (Showing <?php echo (($page - 1) * $per_page) + 1; ?>-<?php echo min($page * $per_page, $total_groups); ?> of <?php echo number_format($total_groups); ?>)
   </p>
 <?php } ?>
</div>
<?php

render_footer();
?>
