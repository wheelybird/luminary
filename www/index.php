<?php

set_include_path( __DIR__ . "/includes/");
include_once "web_functions.inc.php";

render_header();

 if (isset($_GET['logged_in'])) {
 ?>
 <div class="container">
  <div class="alert alert-success">
   <p class="text-center">You're logged in. Select from the menu above.</p>
  </div>
 </div>
 <?php
 }

render_footer();
?>
