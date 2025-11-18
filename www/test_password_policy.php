<?php

set_include_path( ".:" . __DIR__ . "/includes/");

include "web_functions.inc.php";
include "password_policy_functions.inc.php";

echo "<h2>Password Policy Configuration Test</h2>\n";
echo "<pre>\n";

echo "PASSWORD_POLICY_ENABLED: " . var_export($PASSWORD_POLICY_ENABLED, true) . "\n";
echo "PASSWORD_MIN_LENGTH: " . var_export($PASSWORD_MIN_LENGTH, true) . "\n";
echo "PASSWORD_REQUIRE_UPPERCASE: " . var_export($PASSWORD_REQUIRE_UPPERCASE, true) . "\n";
echo "PASSWORD_REQUIRE_LOWERCASE: " . var_export($PASSWORD_REQUIRE_LOWERCASE, true) . "\n";
echo "PASSWORD_REQUIRE_NUMBERS: " . var_export($PASSWORD_REQUIRE_NUMBERS, true) . "\n";
echo "PASSWORD_REQUIRE_SPECIAL: " . var_export($PASSWORD_REQUIRE_SPECIAL, true) . "\n";
echo "PASSWORD_MIN_SCORE: " . var_export($PASSWORD_MIN_SCORE, true) . "\n";
echo "PASSWORD_HISTORY_COUNT: " . var_export($PASSWORD_HISTORY_COUNT, true) . "\n";
echo "PASSWORD_EXPIRY_DAYS: " . var_export($PASSWORD_EXPIRY_DAYS, true) . "\n";
echo "PASSWORD_EXPIRY_WARNING_DAYS: " . var_export($PASSWORD_EXPIRY_WARNING_DAYS, true) . "\n";

echo "\n<h3>Policy Requirements:</h3>\n";
$requirements = password_policy_get_requirements();
if (empty($requirements)) {
    echo "Password policy is disabled\n";
} else {
    foreach ($requirements as $req) {
        echo "- " . htmlspecialchars($req) . "\n";
    }
}

echo "\n<h3>Testing Password Validation:</h3>\n";

$test_passwords = array(
    'abc' => 'Very short',
    'password' => 'No uppercase, numbers, or special chars',
    'Password123' => 'Has uppercase, lowercase, and numbers',
    'Password123!' => 'Has uppercase, lowercase, numbers, and special char',
    'P@ss1' => 'Short but complex',
    'VeryLongPasswordWithNoNumbersOrSpecialChars' => 'Long but not complex'
);

foreach ($test_passwords as $password => $description) {
    echo "\nTesting: '$password' ($description)\n";

    $errors = array();
    $valid = password_policy_validate($password, $errors);

    echo "  Valid: " . ($valid ? "YES" : "NO") . "\n";

    $score = password_policy_get_strength_score($password);
    echo "  Strength Score: $score/4\n";

    $meets_strength = password_policy_check_strength($password, $score);
    echo "  Meets Min Strength: " . ($meets_strength ? "YES" : "NO") . "\n";

    if (!empty($errors)) {
        echo "  Errors:\n";
        foreach ($errors as $error) {
            echo "    - " . htmlspecialchars($error) . "\n";
        }
    }
}

echo "</pre>\n";

?>
