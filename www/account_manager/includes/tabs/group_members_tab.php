<?php

/**
 * Group Members Tab
 * Displays dual-list interface for managing group membership
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<div class="row">
  <div class="dual-list list-left col-md-5">
    <strong>Members</strong>
    <div class="well">
      <div class="select-all-wrapper">
        <input type="checkbox" class="form-check-input selector" id="select_all_left">
        <label class="form-check-label" for="select_all_left">Select all</label>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="SearchDualList" class="form-control" placeholder="search" />
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
      <button id="submit_members" class="btn btn-info" <?php if (count($group_members)==0) print 'disabled'; ?> type="submit" onclick="update_form_with_users()"><?php echo $new_group ? 'Create group' : 'Save'; ?></button>
    </form>
  </div>

  <div class="dual-list list-right col-md-5">
    <strong>Non-members</strong>
    <div class="well">
      <div class="select-all-wrapper">
        <input type="checkbox" class="form-check-input selector" id="select_all_right">
        <label class="form-check-label" for="select_all_right">Select all</label>
      </div>
      <div class="row">
        <div class="col-md-12">
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
