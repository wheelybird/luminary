<?php
// Test ppolicy password change

set_include_path( ".:includes/");
include_once "includes/config_registry.inc.php";
include_once "includes/ldap_functions.inc.php";
include_once "includes/password_policy_functions.inc.php";

// Open connection (which sets connection_type)
$ldap = open_ldap_connection();
echo "LDAP Connection Type: " . $LDAP['connection_type'] . "\n";
echo "PPOLICY_ENABLED: " . ($PPOLICY_ENABLED ? 'TRUE' : 'FALSE') . "\n";
ldap_close($ldap);

// Test password changes
$username = "historytest";
$tests = array(
  array("old" => "FinalTest1!", "new" => "FinalTest2!", "desc" => "Change to FinalTest2!"),
  array("old" => "FinalTest2!", "new" => "FinalTest3!", "desc" => "Change to FinalTest3!"),
  array("old" => "FinalTest3!", "new" => "FinalTest1!", "desc" => "Try to reuse FinalTest1! (should fail)"),
);

foreach ($tests as $i => $test) {
  echo "\nTest " . ($i + 1) . ": " . $test['desc'] . "\n";
  $error = null;
  $result = password_policy_self_service_change($username, $test['old'], $test['new'], $error);

  if ($result) {
    echo "SUCCESS: Password changed\n";
  } else {
    echo "FAILED: " . ($error ? $error : "Unknown error") . "\n";
  }
}

echo "\n";
?>
