<?php

##############################################################################
# CONFIG REGISTRY
##############################################################################

include_once "config_registry.inc.php";

##############################################################################
# MODULE DEFINITIONS
##############################################################################

include_once "modules.inc.php";

##############################################################################
# SANITY CHECKING
# Validate that mandatory configurations are set
##############################################################################

$errors = "";

if (empty($LDAP['uri'])) {
  $errors .= "<div class='alert alert-warning'><p class='text-center'>LDAP_URI isn't set</p></div>\n";
}
if (empty($LDAP['base_dn'])) {
  $errors .= "<div class='alert alert-warning'><p class='text-center'>LDAP_BASE_DN isn't set</p></div>\n";
}
if (empty($LDAP['admin_bind_dn'])) {
  $errors .= "<div class='alert alert-warning'><p class='text-center'>LDAP_ADMIN_BIND_DN isn't set</p></div>\n";
}
if (empty($LDAP['admin_bind_pwd'])) {
  $errors .= "<div class='alert alert-warning'><p class='text-center'>LDAP_ADMIN_BIND_PWD isn't set</p></div>\n";
}
if (empty($LDAP['admins_group'])) {
  $errors .= "<div class='alert alert-warning'><p class='text-center'>LDAP_ADMINS_GROUP isn't set</p></div>\n";
}

if ($errors != "") {
  render_header("Fatal errors",false);
  print $errors;
  render_footer();
  exit(1);
}

?>
