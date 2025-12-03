<?php

/**
 * User Groups Tab
 * Renders the groups management interface for users
 */

// This file should only be included, never accessed directly
if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<div class="row">
  <div class="dual-list list-left col-md-5">
    <strong>Member of</strong>
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
      <ul class="list-group" id="member_of_list">
        <?php
        foreach ($member_of as $group) {
          $group_display = htmlspecialchars(decode_ldap_value($group), ENT_QUOTES, 'UTF-8');
          if ($group == $LDAP["admins_group"] and $USER_ID == $account_identifier) {
            print "<div class='list-group-item' style='opacity: 0.5; pointer-events:none;'>{$group_display}</div>\n";
          } else {
            print "<li class='list-group-item'>{$group_display}</li>\n";
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
    <form id="update_with_groups" action="<?php print $CURRENT_PAGE ?>" method="post">
      <input type="hidden" name="update_member_of">
      <input type="hidden" name="account_identifier" value="<?php print $account_identifier; ?>">
    </form>
    <button id="submit_members" class="btn btn-info" disabled type="submit" onclick="update_form_with_groups()">Save</button>
  </div>

  <div class="dual-list list-right col-md-5">
    <strong>Not a member of</strong>
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
        foreach ($not_member_of as $group) {
          $group_display = htmlspecialchars(decode_ldap_value($group), ENT_QUOTES, 'UTF-8');
          print "<li class='list-group-item'>{$group_display}</li>\n";
        }
        ?>
      </ul>
    </div>
  </div>
</div>
