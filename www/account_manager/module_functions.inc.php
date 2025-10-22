<?php



##################################

function render_submenu() {

  global $THIS_MODULE_PATH, $MFA_ENABLED;

  $submodules = array( 'users' => 'index.php',
                       'groups' => 'groups.php'
                     );

  if ($MFA_ENABLED == TRUE) {
    $submodules['mfa status'] = 'mfa_status.php';
  }
  ?>
   <nav class="navbar navbar-default">
    <div class="container-fluid">
     <ul class="nav navbar-nav">
      <?php
      foreach ($submodules as $submodule => $path) {

       if (basename($_SERVER['SCRIPT_FILENAME']) == $path) {
        print "<li class='active'>";
       }
       else {
        print '<li>';
       }
       print "<a href='{$THIS_MODULE_PATH}/{$path}'>" . ucwords($submodule) . "</a></li>\n";

      }
     ?>
     </ul>
    </div>
   </nav>
  <?php
 }

?>
