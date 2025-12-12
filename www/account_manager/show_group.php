<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";
include_once "audit_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

// Define constant to allow includes
define('LDAP_USER_MANAGER', true);

// Include tab configuration
include_once __DIR__ . '/includes/group_tab_config.php';

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$ldap_connection = open_ldap_connection();

if (!isset($_POST['group_name']) and !isset($_GET['group_name'])) {
?>
 <div class="container">
  <div class="alert alert-danger">
   <p class="text-center">The group name is missing.</p>
  </div>
 </div>
<?php
 render_footer();
 exit(0);
}
else {
  $group_cn = (isset($_POST['group_name']) ? $_POST['group_name'] : $_GET['group_name']);
  $group_cn = urldecode($group_cn);
}

if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE and !preg_match("/$USERNAME_REGEX/u",$group_cn)) {
?>
 <div class="container">
  <div class="alert alert-danger">
   <p class="text-center">The group name is invalid.</p>
  </div>
 </div>
<?php
 render_footer();
 exit(0);
}


######################################################################################

$initialise_group = FALSE;
$new_group = FALSE;
$group_exists = FALSE;

$create_group_message = "Add members to create the new group";
$current_members = array();
$full_dn = $create_group_message;
$has_been = "";

$attribute_map = $LDAP['default_group_attribute_map'];
if (isset($LDAP['group_additional_attributes'])) {
  $attribute_map = ldap_complete_attribute_array($attribute_map,$LDAP['group_additional_attributes']);
}

$to_update = array();
$this_group = array();

if (isset($_POST['new_group'])) {
  $new_group = TRUE;
}
elseif (isset($_POST['initialise_group'])) {
  $initialise_group = TRUE;
  $full_dn = "{$LDAP['group_attribute']}=$group_cn,{$LDAP['group_dn']}";
  $has_been = "created";
  // Group will be created by handler, then we'll fetch it after handlers run
}
else {
  $this_group = ldap_get_group_entry($ldap_connection,$group_cn);
  if ($this_group) {
    $current_members = ldap_get_group_members($ldap_connection,$group_cn);
    $full_dn = $this_group[0]['dn'];
    $has_been = "updated";
    $group_exists = TRUE;
  }
  else {
    $new_group = TRUE;
  }
}

foreach ($attribute_map as $attribute => $attr_r) {

  if (isset($this_group[0][$attribute]) and $this_group[0][$attribute]['count'] > 0) {
    $$attribute = $this_group[0][$attribute];
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

  if (isset($_POST[$attribute])) {

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

if (!isset($gidnumber[0]) or !is_numeric($gidnumber[0])) {
  if ($new_group or $initialise_group) {
    // For new groups, use next available GID (highest + 1) to prevent duplicates (fixes #230)
    $gidnumber[0]=ldap_get_highest_id($ldap_connection,$type="gid") + 1;
  } else {
    // For existing groups without a GID, use highest
    $gidnumber[0]=ldap_get_highest_id($ldap_connection,$type="gid");
  }
  $gidnumber['count']=1;
}

######################################################################################

$all_accounts = ldap_get_user_list($ldap_connection);
$all_people = array();

foreach ($all_accounts as $this_person => $attrs) {
  array_push($all_people, $this_person);
}

$non_members = array_diff($all_people,$current_members);
$group_members = $current_members;

// DEBUG: Check state values BEFORE handlers
error_log("DEBUG show_group.php [BEFORE handlers]: new_group=" . ($new_group ? 'TRUE' : 'FALSE') .
          ", group_exists=" . ($group_exists ? 'TRUE' : 'FALSE') .
          ", initialise_group=" . ($initialise_group ? 'TRUE' : 'FALSE') .
          ", MFA_FEATURE_ENABLED=" . ($MFA_FEATURE_ENABLED ? 'TRUE' : 'FALSE'));

// Get initial tab configuration (needed to know which handlers to include)
$group_tabs = get_group_tabs_config('show_group', $new_group, $group_exists, $attribute_map);

// Include all tab handlers directly (in main scope for variable access)
foreach ($group_tabs as $tab) {
  if (!empty($tab['handler_file'])) {
    $handler_path = __DIR__ . '/includes/' . $tab['handler_file'];
    if (file_exists($handler_path)) {
      include_once $handler_path;
    }
  }
}

// Form handlers are now in separate files:
// - includes/handlers/group_members_handler.php
// - includes/handlers/group_mfa_handler.php
// They are included above via the tab configuration loop

// After handlers run, re-fetch group data if it was just created
if ($initialise_group) {
  $this_group = ldap_get_group_entry($ldap_connection, $group_cn);
  if ($this_group) {
    $current_members = ldap_get_group_members($ldap_connection, $group_cn);
    $group_exists = TRUE;
    $new_group = FALSE;
  }
}

// DEBUG: Check state values AFTER handlers
error_log("DEBUG show_group.php [AFTER handlers]: new_group=" . ($new_group ? 'TRUE' : 'FALSE') .
          ", group_exists=" . ($group_exists ? 'TRUE' : 'FALSE') .
          ", initialise_group=" . ($initialise_group ? 'TRUE' : 'FALSE'));

// Re-configure tabs with updated state (group now exists after creation)
$group_tabs = get_group_tabs_config('show_group', $new_group, $group_exists, $attribute_map);

// DEBUG: Show which tabs are enabled
error_log("DEBUG show_group.php: Enabled tabs: " . implode(', ', array_keys($group_tabs)));

ldap_close($ldap_connection);

?>

<script type="text/javascript">

 function show_delete_group_button() {

  var group_del_submit = document.getElementById('delete_group');
  group_del_submit.classList.replace('invisible','visible');


 }


 function update_form_with_users() {

  var members_form = document.getElementById('group_members');
  var member_list_ul = document.getElementById('membership_list');

  var member_list = member_list_ul.getElementsByTagName("li");

  for (var i = 0; i < member_list.length; ++i) {
    var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = 'membership[]';
        hidden.value = member_list[i]['textContent'];
        members_form.appendChild(hidden);

  }

  members_form.submit();

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
            if (document.getElementById('membership_list')) {
                document.getElementById('submit_members').disabled = false;
                const submitAttributes = document.getElementById('submit_attributes');
                if (submitAttributes) {
                    submitAttributes.disabled = false;
                }
            }
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
</style>


<div class="container">
  <div class="col-md-12">

    <!-- Page Header -->
    <div class="row mb-3">
      <div class="col-md-8">
        <h2><?php print htmlspecialchars(decode_ldap_value($group_cn), ENT_QUOTES, 'UTF-8'); ?><?php if ($group_cn == $LDAP["admins_group"]) { print " <sup>(admin group)</sup>" ; } ?></h2>
        <p class="text-muted"><?php print htmlspecialchars(decode_ldap_value($full_dn), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <div class="col-md-4 text-end">
        <button class="btn btn-warning" onclick="show_delete_group_button();" <?php if ($group_cn == $LDAP["admins_group"]) { print "disabled"; } ?>>Delete group</button>
        <form action="<?php print "{$THIS_MODULE_PATH}"; ?>/groups.php" method="post" enctype="multipart/form-data" style="display: inline;">
          <input type="hidden" name="delete_group" value="<?php print $group_cn; ?>">
          <button class="btn btn-danger invisible" id="delete_group">Confirm deletion</button>
        </form>
      </div>
    </div>

    <!-- Tab Navigation -->
    <?php render_tab_navigation($group_tabs, 'groupTabs'); ?>

    <!-- Tab Content -->
    <div class="tab-content" id="groupTabContent">
    <?php
      foreach ($group_tabs as $tab) {
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

<?php render_tab_persistence_js('show_group_active_tab', '#groupTabs'); ?>

<?php render_footer(); ?>
