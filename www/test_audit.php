<?php

set_include_path( ".:" . __DIR__ . "/includes/");

include "web_functions.inc.php";
include "audit_functions.inc.php";

echo "Testing audit logging...\n";
echo "AUDIT_ENABLED = " . var_export($AUDIT_ENABLED, true) . "\n";
echo "AUDIT_LOG_FILE = " . var_export($AUDIT_LOG_FILE, true) . "\n";

echo "\nCalling audit_log()...\n";
$result = audit_log('test_action', 'test_target', 'Testing audit logging', 'success', 'test_user');
echo "Result: " . var_export($result, true) . "\n";

if (file_exists($AUDIT_LOG_FILE)) {
    echo "\nLog file exists. Contents:\n";
    echo file_get_contents($AUDIT_LOG_FILE);
} else {
    echo "\nLog file does not exist: $AUDIT_LOG_FILE\n";
}

?>
