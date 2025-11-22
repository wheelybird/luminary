<?php

/**
 * Group Attributes Tab
 * Displays group attribute fields for editing
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

?>
<form id="group_attributes" action="<?php print $CURRENT_PAGE; ?>" method="post" enctype="multipart/form-data">
  <input type="hidden" name="update_members">
  <input type="hidden" name="group_name" value="<?php print urlencode($group_cn); ?>">
  <?php if ($new_group == TRUE) { ?><input type="hidden" name="initialise_group"><?php } ?>

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
          <button id="submit_attributes" class="btn btn-info" <?php if (count($group_members)==0) print 'disabled'; ?> type="submit" tabindex="<?php print $tabindex; ?>">Save</button>
        </div>
      </div>
    </div>
  </div>
</form>
