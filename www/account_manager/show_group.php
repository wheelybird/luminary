<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$ldap_connection = open_ldap_connection();

if (!isset($_POST['group_name']) and !isset($_GET['group_name'])) {
?>
 <div class="alert alert-danger">
  <p class="text-center">The group name is missing.</p>
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
 <div class="alert alert-danger">
  <p class="text-center">The group name is invalid.</p>
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

if (isset($_POST["update_members"])) {

  $updated_membership = array();

  // Check if membership array exists before iterating (fixes #230 - empty group validation)
  if (isset($_POST['membership']) && is_array($_POST['membership'])) {
    foreach ($_POST['membership'] as $index => $member) {
      if (is_numeric($index)) {
       array_push($updated_membership, trim($member));
      }
    }
  }

  if ($group_cn == $LDAP['admins_group'] and !array_search($USER_ID, $updated_membership)){
    array_push($updated_membership,$USER_ID);
  }

  $members_to_del = array_diff($current_members,$updated_membership);
  $members_to_add = array_diff($updated_membership,$current_members);

  if ($initialise_group == TRUE) {

    $initial_member = array_shift($members_to_add);
    $group_add = ldap_new_group($ldap_connection,$group_cn,$initial_member,$to_update);
    if (!$group_add) {
      render_alert_banner("There was a problem creating the group.  See the logs for more information.","danger",10000);
      $group_exists = FALSE;
      $new_group = TRUE;
    }
    else {
      $group_exists = TRUE;
      $new_group = FALSE;
    }

  }

  if ($group_exists == TRUE) {

    if ($initialise_group != TRUE and count($to_update) > 0) {

      if (isset($this_group[0]['objectclass'])) {
        $existing_objectclasses = $this_group[0]['objectclass'];
        unset($existing_objectclasses['count']);
        if ($existing_objectclasses != $LDAP['group_objectclasses']) { $to_update['objectclass'] = $LDAP['group_objectclasses']; }
      }

      $updated_attr = ldap_update_group_attributes($ldap_connection,$group_cn,$to_update);

      if ($updated_attr) {
        render_alert_banner("The group attributes have been updated.");
      }
      else {
        render_alert_banner("There was a problem updating the group attributes.  See the logs for more information.","danger",15000);
      }

    }

    foreach ($members_to_add as $this_member) {
      ldap_add_member_to_group($ldap_connection,$group_cn,$this_member);
    }

    foreach ($members_to_del as $this_member) {
      ldap_delete_member_from_group($ldap_connection,$group_cn,$this_member);
    }

    $non_members = array_diff($all_people,$updated_membership);
    $group_members = $updated_membership;

    $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);
    if ($rfc2307bis_available == TRUE and count($group_members) == 0) {

      $group_members = ldap_get_group_members($ldap_connection,$group_cn);
      $non_members = array_diff($all_people,$group_members);
      render_alert_banner("Groups can't be empty, so the final member hasn't been removed.  You could try deleting the group","danger",15000);
    }
    else {
      render_alert_banner("The group has been {$has_been}.");
    }

  }
  else {

    $group_members = array();
    $non_members = $all_people;

  }

}
else {

  $group_members = $current_members;

}

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
        selector.addEventListener('click', function() {
            const well = this.closest('.well');
            const icon = this.querySelector('i');
            if (!this.classList.contains('selected')) {
                this.classList.add('selected');
                well.querySelectorAll('ul li:not(.active)').forEach(function(li) {
                    li.classList.add('active');
                });
                icon.classList.remove('bi-square');
                icon.classList.add('bi-check-square');
            } else {
                this.classList.remove('selected');
                well.querySelectorAll('ul li.active').forEach(function(li) {
                    li.classList.remove('active');
                });
                icon.classList.remove('bi-check-square');
                icon.classList.add('bi-square');
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
</style>


<div class="container">
  <div class="col-md-12">
    <div class="accordion">
      <div class="card">

        <div class="card-header clearfix">
          <h3 class="float-start" style="padding-top: 7.5px;"><?php print htmlspecialchars(decode_ldap_value($group_cn), ENT_QUOTES, 'UTF-8'); ?><?php if ($group_cn == $LDAP["admins_group"]) { print " <sup>(admin group)</sup>" ; } ?></h3>
          <button class="btn btn-warning float-end" onclick="show_delete_group_button();" <?php if ($group_cn == $LDAP["admins_group"]) { print "disabled"; } ?>>Delete group</button>
          <form action="<?php print "{$THIS_MODULE_PATH}"; ?>/groups.php" method="post" enctype="multipart/form-data"><input type="hidden" name="delete_group" value="<?php print $group_cn; ?>"><button class="btn btn-danger float-end invisible" id="delete_group">Confirm deletion</button></form>
        </div>

        <ul class="list-group list-group-flush">
          <li class="list-group-item"><?php print htmlspecialchars(decode_ldap_value($full_dn), ENT_QUOTES, 'UTF-8'); ?></li>
        </li>

        <div class="card-body">
          <div class="row">
            <div class="dual-list list-left col-md-5">
              <strong>Members</strong>
              <div class="well">
                <div class="row">
                  <div class="col-md-10">
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-search"></i></span>
                      <input type="text" name="SearchDualList" class="form-control" placeholder="search" />
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="btn-group">
                      <a class="btn btn-secondary selector" title="select all"><i class="bi bi-square"></i></a>
                    </div>
                  </div>
                </div>
                <ul class="list-group" id="membership_list">
                  <?php
                  foreach ($group_members as $member) {
                    $member_display = htmlspecialchars(decode_ldap_value($member), ENT_QUOTES, 'UTF-8');
                    if ($group_cn == $LDAP['admins_group'] and $member == $USER_ID) {
                      print "<div class='list-group-item' style='opacity: 0.5; pointer-events:none;'>{$member_display}</div>\n";
                    }
                    else {
                      print "<li class='list-group-item'>{$member_display}</li>\n";
                    }
                  }
                  ?>
                </ul>
              </div>
            </div>
            <div class="list-arrows col-md-1 text-center">
              <button class="btn btn-secondary btn-sm move-left">
                <i class="bi bi-chevron-left"></i>
              </button>
              <button class="btn btn-secondary btn-sm move-right">
                <i class="bi bi-chevron-right"></i>
              </button>
              <form id="group_members" action="<?php print $CURRENT_PAGE; ?>" method="post">
                <input type="hidden" name="update_members">
                <input type="hidden" name="group_name" value="<?php print urlencode($group_cn); ?>">
                <?php if ($new_group == TRUE) { ?><input type="hidden" name="initialise_group"><?php } ?>
                <button id="submit_members" class="btn btn-info" <?php if (count($group_members)==0) print 'disabled'; ?> type="submit" onclick="update_form_with_users()">Save</button>
            </div>

            <div class="dual-list list-right col-md-5">
              <strong>Available accounts</strong>
              <div class="well">
                <div class="row">
                  <div class="col-md-2">
                    <div class="btn-group">
                      <a class="btn btn-secondary selector" title="select all"><i class="bi bi-square"></i></a>
                    </div>
                  </div>
                  <div class="col-md-10">
                    <div class="input-group">
                      <input type="text" name="SearchDualList" class="form-control" placeholder="search" />
                      <span class="input-group-text"><i class="bi bi-search"></i></span>
                    </div>
                  </div>
                </div>
                <ul class="list-group">
                  <?php
                   foreach ($non_members as $nonmember) {
                     $nonmember_display = htmlspecialchars(decode_ldap_value($nonmember), ENT_QUOTES, 'UTF-8');
                     print "<li class='list-group-item'>{$nonmember_display}</li>\n";
                   }
                 ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
<?php

if (count($attribute_map) > 0) { ?>
      <div class="card">
        <div class="card-header clearfix">
          <h3 class="float-start" style="padding-top: 7.5px;">Group attributes</h3>
        </div>
        <div class="card-body">
          <div class="col-md-8">
            <?php
              $tabindex=1;
              foreach ($attribute_map as $attribute => $attr_r) {
                $label = $attr_r['label'];
                if (isset($$attribute)) { $these_values=$$attribute; } else { $these_values = array(); }
                print "<div class='row'>";
                $dl_identifider = ($full_dn != $create_group_message) ? $full_dn : "";
                if (isset($attr_r['inputtype'])) { $inputtype = $attr_r['inputtype']; } else { $inputtype=""; }
                render_attribute_fields($attribute,$label,$these_values,$dl_identifider,"",$inputtype,$tabindex);
                print "</div>";
                $tabindex++;
              }
            ?>
            <div class="row">
              <div class="col-md-4 offset-md-3">
                <div class="row mb-3">
                  <button id="submit_attributes" class="btn btn-info" <?php if (count($group_members)==0) print 'disabled'; ?> type="submit" tabindex="<?php print $tabindex; ?>" onclick="update_form_with_users()">Save</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
<?php } ?>
              </form>
    </div>
  </div>
</div>
<?php render_footer(); ?>
