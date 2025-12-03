<?php

/**
 * User Tab Configuration
 * Defines all tabs for the user management interface
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

/**
 * Define user tabs configuration
 * Each tab has:
 * - id: unique identifier
 * - label: display name
 * - icon: Bootstrap icon class
 * - tab_file: path to tab content file
 * - handler_file: path to form handler file (optional)
 * - enabled_check: callback function to determine if tab should be shown
 * - active: whether this is the default active tab
 */

function get_user_tabs_config($context) {
  global $MFA_FULLY_OPERATIONAL, $PASSWORD_POLICY_ENABLED, $PPOLICY_ENABLED,
         $PASSWORD_EXPIRY_DAYS, $LIFECYCLE_ENABLED, $ACCOUNT_EXPIRY_ENABLED;

  $tabs = [
    'details' => [
      'id' => 'details',
      'label' => 'Details',
      'icon' => 'bi-person-badge',
      'tab_file' => 'tabs/user_details_tab.php',
      'handler_file' => 'handlers/user_details_handler.php',
      'enabled' => true,
      'active' => true
    ],
    'mfa' => [
      'id' => 'mfa',
      'label' => 'MFA Status',
      'icon' => 'bi-shield-lock',
      'tab_file' => 'tabs/user_mfa_tab.php',
      'handler_file' => 'handlers/user_mfa_handler.php',
      'enabled' => ($MFA_FULLY_OPERATIONAL == TRUE),
      'active' => false
    ],
    'password' => [
      'id' => 'password',
      'label' => 'Password Status',
      'icon' => 'bi-key',
      'tab_file' => 'tabs/user_password_tab.php',
      'handler_file' => null, // No separate handler needed
      'enabled' => ($PASSWORD_POLICY_ENABLED == TRUE && $PPOLICY_ENABLED == TRUE && $PASSWORD_EXPIRY_DAYS > 0),
      'active' => false
    ],
    'lifecycle' => [
      'id' => 'lifecycle',
      'label' => 'Account Lifecycle',
      'icon' => 'bi-arrow-repeat',
      'tab_file' => 'tabs/user_lifecycle_tab.php',
      'handler_file' => 'handlers/user_lifecycle_handler.php',
      'enabled' => ($LIFECYCLE_ENABLED == TRUE && $ACCOUNT_EXPIRY_ENABLED == TRUE),
      'active' => false
    ],
    'groups' => [
      'id' => 'groups',
      'label' => 'Groups',
      'icon' => 'bi-people',
      'tab_file' => 'tabs/user_groups_tab.php',
      'handler_file' => 'handlers/user_groups_handler.php',
      'enabled' => true,
      'active' => false
    ]
  ];

  // Filter to only enabled tabs
  return array_filter($tabs, function($tab) {
    return $tab['enabled'];
  });
}

?>
