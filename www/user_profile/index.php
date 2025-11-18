<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "totp_functions.inc.php";

set_page_access("user");

render_header("$ORGANISATION_NAME user profile");

$ldap_connection = open_ldap_connection();

// Build attribute map for user-editable attributes with friendly labels
$attribute_map = array();

// Map attribute names to input types and friendly labels
$attribute_config = array(
  'telephonenumber' => array('label' => 'Telephone Number', 'inputtype' => 'tel'),
  'mobile' => array('label' => 'Mobile Number', 'inputtype' => 'tel'),
  'displayname' => array('label' => 'Display Name', 'inputtype' => 'text'),
  'description' => array('label' => 'About Me', 'inputtype' => 'textarea'),
  'title' => array('label' => 'Job Title', 'inputtype' => 'text'),
  'jpegphoto' => array('label' => 'Profile Photo', 'inputtype' => 'binary'),
  'sshpublickey' => array('label' => 'SSH Public Keys', 'inputtype' => 'multipleinput'),

  // Common additional attributes with good defaults
  'homephone' => array('label' => 'Home Phone', 'inputtype' => 'tel'),
  'facsimiletelephonenumber' => array('label' => 'Fax Number', 'inputtype' => 'tel'),
  'pager' => array('label' => 'Pager', 'inputtype' => 'tel'),
  'employeetype' => array('label' => 'Employee Type', 'inputtype' => 'text'),
  'employeenumber' => array('label' => 'Employee Number', 'inputtype' => 'text'),
  'preferredlanguage' => array('label' => 'Preferred Language', 'inputtype' => 'text'),
  'street' => array('label' => 'Street Address', 'inputtype' => 'text'),
  'postaladdress' => array('label' => 'Postal Address', 'inputtype' => 'textarea'),
  'postalcode' => array('label' => 'Postal Code', 'inputtype' => 'text'),
  'l' => array('label' => 'City', 'inputtype' => 'text'),
  'st' => array('label' => 'State/Province', 'inputtype' => 'text'),
  'postofficebox' => array('label' => 'P.O. Box', 'inputtype' => 'text'),
  'usercertificate' => array('label' => 'Certificate', 'inputtype' => 'binary'),
  'labeleduri' => array('label' => 'Website', 'inputtype' => 'url'),
  'carlicense' => array('label' => 'Car License', 'inputtype' => 'text'),
  'roomnumber' => array('label' => 'Room Number', 'inputtype' => 'text'),
  'departmentnumber' => array('label' => 'Department', 'inputtype' => 'text'),
  'initials' => array('label' => 'Initials', 'inputtype' => 'text'),
);

// Build attribute map from editable attributes list
foreach ($USER_EDITABLE_ATTRIBUTES as $attr) {
  $attr_lower = strtolower($attr);

  // Use predefined config if available, otherwise create default
  if (isset($attribute_config[$attr_lower])) {
    $attribute_map[$attr_lower] = $attribute_config[$attr_lower];
  } else {
    // Fallback: create readable label from attribute name
    $label = ucwords(str_replace('_', ' ', $attr));
    $attribute_map[$attr_lower] = array(
      'label' => $label,
      'inputtype' => 'text'
    );
  }
}

// Load current user's LDAP entry
$user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
  "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
  array_merge(array('dn', 'cn', 'givenname', 'sn'), array_keys($attribute_map)));

if (!$user_search) {
  render_alert_banner("Failed to load user profile.", "danger", 15000);
  render_footer();
  exit(1);
}

$user = ldap_get_entries($ldap_connection, $user_search);

if ($user['count'] == 0) {
  render_alert_banner("User not found.", "danger", 15000);
  render_footer();
  exit(1);
}

$dn = $user[0]['dn'];
$to_update = array();

// Load current attribute values
foreach ($attribute_map as $attribute => $attr_config) {
  if (isset($user[0][$attribute]) && $user[0][$attribute]['count'] > 0) {
    $$attribute = $user[0][$attribute];
  } else {
    $$attribute = array();
  }

  // Handle file uploads
  if (isset($_FILES[$attribute]['size']) && $_FILES[$attribute]['size'] > 0) {

    // Special validation for jpegPhoto
    if ($attribute == 'jpegphoto') {
      $upload_error = null;

      // Check file size (500KB limit for LDAP performance)
      $max_size = 500 * 1024; // 500KB in bytes
      if ($_FILES[$attribute]['size'] > $max_size) {
        $upload_error = "Profile photo must be smaller than 500KB. Please resize your image.";
      }

      // Check MIME type
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime_type = $finfo->file($_FILES[$attribute]['tmp_name']);
      if ($mime_type !== 'image/jpeg') {
        $upload_error = "Profile photo must be a JPEG image. Uploaded file type: " . htmlspecialchars($mime_type);
      }

      // Verify it's actually a valid JPEG by attempting to load it
      $image_check = @imagecreatefromjpeg($_FILES[$attribute]['tmp_name']);
      if ($image_check === false) {
        $upload_error = "The uploaded file is not a valid JPEG image.";
      } else {
        imagedestroy($image_check);
      }

      if ($upload_error) {
        render_alert_banner($upload_error, "danger", 15000);
        // Skip this file upload
        continue;
      }
    }

    $this_attribute = array();
    $this_attribute['count'] = 1;
    $this_attribute[0] = file_get_contents($_FILES[$attribute]['tmp_name']);
    $$attribute = $this_attribute;
    $to_update[$attribute] = $this_attribute;
    unset($to_update[$attribute]['count']);
  }

  // Handle form submission
  if (isset($_POST['update_profile']) && isset($_POST[$attribute])) {
    $this_attribute = array();

    if (is_array($_POST[$attribute])) {
      // Multi-valued attribute
      foreach ($_POST[$attribute] as $key => $value) {
        if ($value != "") {
          $this_attribute[$key] = trim($value);
        }
      }
      $this_attribute['count'] = count($this_attribute);
    } elseif ($_POST[$attribute] != "") {
      // Single-valued attribute
      $this_attribute['count'] = 1;
      $this_attribute[0] = trim($_POST[$attribute]);
    }

    // Check if value changed
    if ($this_attribute != $$attribute) {
      $$attribute = $this_attribute;
      $to_update[$attribute] = $this_attribute;
      unset($to_update[$attribute]['count']);
    }
  }

  // Handle checkbox (boolean) attributes
  if (isset($_POST['update_profile']) && isset($attr_config['inputtype']) && $attr_config['inputtype'] == 'checkbox') {
    if (!isset($_POST[$attribute])) {
      // Checkbox not checked - set to empty
      $this_attribute = array();
      if ($this_attribute != $$attribute) {
        $$attribute = $this_attribute;
        $to_update[$attribute] = array(); // Will delete the attribute
      }
    }
  }
}

// Process form submission
if (isset($_POST['update_profile'])) {

  // Security validation: ensure all attributes being updated are actually editable
  $security_violation = false;
  foreach (array_keys($to_update) as $attr_to_update) {
    if (!is_user_editable($attr_to_update)) {
      error_log("$log_prefix Security violation: User $USER_ID attempted to edit blacklisted attribute: $attr_to_update");
      $security_violation = true;
      break;
    }
  }

  if ($security_violation) {
    render_alert_banner("Security violation: You cannot edit that attribute.", "danger", 15000);
  } elseif (!empty($to_update)) {
    // Perform LDAP update
    $updated_profile = @ldap_mod_replace($ldap_connection, $dn, $to_update);

    if ($updated_profile) {
      render_alert_banner("Profile updated successfully.");

      // Reload user data to show updated values
      $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
        "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
        array_merge(array('dn'), array_keys($attribute_map)));

      if ($user_search) {
        $user = ldap_get_entries($ldap_connection, $user_search);
        if ($user['count'] > 0) {
          foreach ($attribute_map as $attribute => $attr_config) {
            if (isset($user[0][$attribute]) && $user[0][$attribute]['count'] > 0) {
              $$attribute = $user[0][$attribute];
            } else {
              $$attribute = array();
            }
          }
        }
      }
    } else {
      ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
      error_log("$log_prefix Failed to update profile for $USER_ID: " . ldap_error($ldap_connection) . " -- " . $detailed_err);
      render_alert_banner("Failed to update profile. Please try again.", "danger", 15000);
    }
  } else {
    render_alert_banner("No changes detected.", "info", 4000);
  }
}

// Get user's display name
$display_name = $USER_ID;
if (isset($user[0]['cn'][0])) {
  $display_name = $user[0]['cn'][0];
} elseif (isset($user[0]['givenname'][0]) || isset($user[0]['sn'][0])) {
  $givenname = isset($user[0]['givenname'][0]) ? $user[0]['givenname'][0] : '';
  $sn = isset($user[0]['sn'][0]) ? $user[0]['sn'][0] : '';
  $display_name = trim($givenname . ' ' . $sn);
}

?>

<div class="container">

  <h2>My Profile</h2>
  <p class="text-muted">Manage your personal information and contact details</p>

  <div class="card">
    <div class="card-header">
      <h4 class="card-title"><?php echo htmlspecialchars($display_name); ?></h4>
      <p class="text-muted mb-0">Username: <?php echo htmlspecialchars($USER_ID); ?></p>
    </div>
    <div class="card-body">

      <?php if (empty($attribute_map)) { ?>
        <div class="alert alert-info">
          <p class="text-center">No editable attributes are configured. Contact your administrator to enable user profile editing.</p>
        </div>
      <?php } else { ?>

        <form method="post" enctype="multipart/form-data">

          <?php
          foreach ($attribute_map as $attribute => $attr_config) {
            $label = $attr_config['label'];
            $inputtype = isset($attr_config['inputtype']) ? $attr_config['inputtype'] : 'text';

            // Render the attribute field
            render_attribute_fields($attribute, $label, $$attribute, $USER_ID, "", $inputtype);
          }
          ?>

          <div class="row mb-3">
            <div class="col-sm-9 offset-sm-3">
              <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="bi bi-save"></i> Update Profile
              </button>
              <a href="/" class="btn btn-secondary">
                <i class="bi bi-x-circle"></i> Cancel
              </a>
            </div>
          </div>

        </form>

      <?php } ?>

    </div>
  </div>

  <?php
  // Display password information if password policy is enabled
  if ($PASSWORD_POLICY_ENABLED && $PPOLICY_ENABLED && $PASSWORD_EXPIRY_DAYS > 0) {
    include_once "password_policy_functions.inc.php";

    // Get password information
    $user_search_pwd = ldap_search($ldap_connection, $LDAP['user_dn'],
      "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
      array('dn'));

    if ($user_search_pwd) {
      $user_pwd = ldap_get_entries($ldap_connection, $user_search_pwd);
      if ($user_pwd['count'] > 0) {
        $user_dn_pwd = $user_pwd[0]['dn'];
        $password_changed_time = password_policy_get_changed_time($ldap_connection, $user_dn_pwd);

        if ($password_changed_time) {
          // Calculate password age and expiry
          $timestamp = strtotime($password_changed_time);
          $password_changed_formatted = date('F j, Y \a\t g:i A', $timestamp);
          $age_seconds = time() - $timestamp;
          $password_age_days = floor($age_seconds / 86400);
          $password_expires_in_days = $PASSWORD_EXPIRY_DAYS - $password_age_days;
          $expiry_date = date('F j, Y', $timestamp + ($PASSWORD_EXPIRY_DAYS * 86400));

          // Determine status
          $status_class = 'success';
          $status_text = 'OK';
          if ($password_expires_in_days <= 0) {
            $status_class = 'danger';
            $status_text = 'Expired';
          } elseif ($password_expires_in_days <= $PASSWORD_EXPIRY_WARNING_DAYS) {
            $status_class = 'warning';
            $status_text = 'Expiring Soon';
          }
  ?>

  <div class="card mt-3">
    <div class="card-header">
      <h4 class="card-title">
        Password Information
        <span class="badge bg-<?php echo $status_class; ?> float-end"><?php echo $status_text; ?></span>
      </h4>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Last Changed:</strong></div>
        <div class="col-sm-8"><?php echo $password_changed_formatted; ?></div>
      </div>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Password Age:</strong></div>
        <div class="col-sm-8"><?php echo $password_age_days; ?> day<?php echo $password_age_days != 1 ? 's' : ''; ?></div>
      </div>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Expiry Date:</strong></div>
        <div class="col-sm-8"><?php echo $expiry_date; ?></div>
      </div>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Days Until Expiry:</strong></div>
        <div class="col-sm-8">
          <?php if ($password_expires_in_days > 0): ?>
            <span class="text-<?php echo $status_class; ?>"><?php echo $password_expires_in_days; ?> day<?php echo $password_expires_in_days != 1 ? 's' : ''; ?></span>
          <?php else: ?>
            <span class="text-danger">Password has expired</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-12">
          <a href="<?php echo $SERVER_PATH; ?>change_password" class="btn btn-primary">
            <i class="bi bi-key"></i> Change Password
          </a>
        </div>
      </div>
    </div>
  </div>

  <?php
        }
      }
    }
  }
  ?>

  <?php
  // Display account lifecycle information if enabled
  if ($LIFECYCLE_ENABLED == TRUE && $ACCOUNT_EXPIRY_ENABLED == TRUE) {
    include_once "account_lifecycle_functions.inc.php";

    // Get account expiration information
    $account_days_remaining = null;
    $account_is_expired = account_lifecycle_is_expired($ldap_connection, $user_dn_profile, $account_days_remaining);
    $account_should_warn = account_lifecycle_should_warn($ldap_connection, $user_dn_profile, $account_days_remaining);
    $account_expiry_date_formatted = account_lifecycle_get_expiry_date_formatted($ldap_connection, $user_dn_profile, 'F j, Y');
    $account_expiry_timestamp = account_lifecycle_get_expiry_timestamp($ldap_connection, $user_dn_profile);
    $account_create_time = account_lifecycle_get_create_time($ldap_connection, $user_dn_profile);

    // Convert LDAP timestamps to formatted strings
    $account_created_formatted = null;
    if ($account_create_time) {
      $create_timestamp = account_lifecycle_ldap_to_timestamp($account_create_time);
      if ($create_timestamp) {
        $account_created_formatted = date('F j, Y', $create_timestamp);
      }
    }

    // Only display if account has an expiration date or is approaching expiry
    if ($account_expiry_date_formatted !== null || $account_is_expired || $account_should_warn) {

      // Determine account status
      $account_status_class = 'success';
      $account_status_text = 'OK';
      if ($account_is_expired) {
        $account_status_class = 'danger';
        $account_status_text = 'Expired';
      } elseif ($account_should_warn) {
        $account_status_class = 'warning';
        $account_status_text = 'Expiring Soon';
      }
  ?>

  <div class="card mt-3">
    <div class="card-header">
      <h4 class="card-title">
        Account Information
        <span class="badge bg-<?php echo $account_status_class; ?> float-end"><?php echo $account_status_text; ?></span>
      </h4>
    </div>
    <div class="card-body">
      <?php if ($account_created_formatted): ?>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Account Created:</strong></div>
        <div class="col-sm-8"><?php echo $account_created_formatted; ?></div>
      </div>
      <?php endif; ?>
      <?php if ($account_expiry_date_formatted !== null): ?>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Expiration Date:</strong></div>
        <div class="col-sm-8"><?php echo $account_expiry_date_formatted; ?></div>
      </div>
      <div class="row mb-3">
        <div class="col-sm-4"><strong>Days Until Expiry:</strong></div>
        <div class="col-sm-8">
          <?php if ($account_is_expired): ?>
            <span class="text-danger">Account expired <?php echo abs($account_days_remaining); ?> day<?php echo abs($account_days_remaining) != 1 ? 's' : ''; ?> ago</span>
          <?php elseif ($account_days_remaining !== null && $account_days_remaining > 0): ?>
            <span class="text-<?php echo $account_status_class; ?>"><?php echo $account_days_remaining; ?> day<?php echo $account_days_remaining != 1 ? 's' : ''; ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($account_should_warn || $account_is_expired): ?>
      <div class="alert alert-<?php echo $account_status_class; ?>">
        <?php if ($account_is_expired): ?>
          <strong>Your account has expired.</strong> Please contact your administrator for assistance.
        <?php else: ?>
          <strong>Your account will expire soon.</strong> Please contact your administrator if you need an extension.
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php
    }
  }
  ?>

</div>

<?php
ldap_close($ldap_connection);
render_footer();
?>
