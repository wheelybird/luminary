<?php



##################################

function render_submenu() {

  global $THIS_MODULE_PATH, $MFA_ENABLED;

  $submodules = array( 'users' => 'index.php',
                       'groups' => 'groups.php'
                     );

  if ($MFA_ENABLED == TRUE) {
    $submodules['mfa_status'] = 'mfa_status.php';
  }

  // Submodule display names (optional - if not set, ucwords of key is used)
  $submodule_names = array(
    'users' => 'Users',
    'groups' => 'Groups',
    'mfa_status' => 'MFA Status'
  );

  ?>
   <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <div class="container-fluid">
     <ul class="navbar-nav">
      <?php
      foreach ($submodules as $submodule => $path) {

       // Use display name if available, otherwise use ucwords of key
       $display_name = isset($submodule_names[$submodule]) ? $submodule_names[$submodule] : ucwords($submodule);

       if (basename($_SERVER['SCRIPT_FILENAME']) == $path) {
        print "<li class='nav-item'><a class='nav-link active' href='{$THIS_MODULE_PATH}/{$path}'>" . $display_name . "</a></li>\n";
       }
       else {
        print "<li class='nav-item'><a class='nav-link' href='{$THIS_MODULE_PATH}/{$path}'>" . $display_name . "</a></li>\n";
       }

      }
     ?>
     </ul>
    </div>
   </nav>
  <?php
 }

?>
