<?php

/**
 * Group Tab Configuration
 * Defines all tabs for the group management interface
 */

if (!defined('LDAP_USER_MANAGER')) {
  die('Direct access not permitted');
}

/**
 * Define group tabs configuration
 * Each tab has:
 * - id: unique identifier
 * - label: display name
 * - icon: Bootstrap icon class
 * - tab_file: path to tab content file
 * - handler_file: path to form handler file (optional)
 * - enabled: whether this tab should be shown
 * - active: whether this is the default active tab
 */

function get_group_tabs_config($context, $new_group = false, $group_exists = true, $attribute_map = array()) {
  global $MFA_FEATURE_ENABLED;

  $tabs = [
    'members' => [
      'id' => 'members',
      'label' => 'Members',
      'icon' => 'bi-people',
      'tab_file' => 'tabs/group_members_tab.php',
      'handler_file' => 'handlers/group_members_handler.php',
      'enabled' => true,
      'active' => true
    ],
    'mfa' => [
      'id' => 'mfa',
      'label' => 'MFA Settings',
      'icon' => 'bi-shield-lock',
      'tab_file' => 'tabs/group_mfa_tab.php',
      'handler_file' => 'handlers/group_mfa_handler.php',
      'enabled' => ($MFA_FEATURE_ENABLED == TRUE && !$new_group && $group_exists),
      'active' => false
    ],
    'attributes' => [
      'id' => 'attributes',
      'label' => 'Attributes',
      'icon' => 'bi-gear',
      'tab_file' => 'tabs/group_attributes_tab.php',
      'handler_file' => 'handlers/group_attributes_handler.php',
      'enabled' => (count($attribute_map) > 0 && !$new_group),
      'active' => false
    ]
  ];

  // Filter to only enabled tabs
  return array_filter($tabs, function($tab) {
    return $tab['enabled'];
  });
}

?>
