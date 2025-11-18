<?php

set_include_path( ".:" . __DIR__ . "/includes/");

include "web_functions.inc.php";
include "ldap_functions.inc.php";
include "password_policy_functions.inc.php";

echo "<h2>ppolicy Overlay Detection Test</h2>\n";
echo "<pre>\n";

$ldap_connection = open_ldap_connection();

if (!$ldap_connection) {
    echo "ERROR: Could not connect to LDAP\n";
    exit(1);
}

echo "Testing ppolicy overlay availability...\n\n";

$ppolicy_available = password_policy_ppolicy_available($ldap_connection);

echo "ppolicy overlay available: " . ($ppolicy_available ? "YES" : "NO") . "\n\n";

if ($ppolicy_available) {
    echo "✓ Password history and expiry features are supported\n";
    echo "✓ Operational attributes available: pwdChangedTime, pwdHistory\n";
} else {
    echo "✗ ppolicy overlay not detected\n";
    echo "✗ Password history and expiry features will be disabled\n";
    echo "\nTo enable ppolicy overlay:\n";
    echo "1. Load ppolicy module in OpenLDAP configuration\n";
    echo "2. Configure ppolicy overlay on your database\n";
    echo "3. Restart OpenLDAP server\n";
}

echo "\nPassword Policy Feature Status:\n";
echo "-------------------------------\n";
echo "PASSWORD_POLICY_ENABLED: " . var_export($PASSWORD_POLICY_ENABLED, true) . "\n";

if ($PASSWORD_POLICY_ENABLED) {
    echo "\nFeatures:\n";
    echo "  - Complexity validation: ALWAYS AVAILABLE\n";
    echo "  - Strength scoring: ALWAYS AVAILABLE\n";
    echo "  - Password history: " . ($ppolicy_available ? "AVAILABLE" : "DISABLED (requires ppolicy)") . "\n";
    echo "  - Password expiry: " . ($ppolicy_available ? "AVAILABLE" : "DISABLED (requires ppolicy)") . "\n";
}

ldap_close($ldap_connection);

echo "</pre>\n";

?>
