<?php

 $log_prefix="";

 # User account defaults

 $DEFAULT_USER_GROUP = (getenv('DEFAULT_USER_GROUP') ? getenv('DEFAULT_USER_GROUP') : 'everybody');
 $DEFAULT_USER_SHELL = (getenv('DEFAULT_USER_SHELL') ? getenv('DEFAULT_USER_SHELL') : '/bin/bash');
 $ENFORCE_SAFE_SYSTEM_NAMES = ((strcasecmp(getenv('ENFORCE_SAFE_SYSTEM_NAMES'),'FALSE') == 0) ? FALSE : TRUE);

 # USERNAME_FORMAT template variables:
 #   {first_name}          - Full first name (e.g., "john")
 #   {first_name_initial}  - First letter of first name (e.g., "j")
 #   {last_name}           - Full last name (e.g., "smith")
 #   {last_name_initial}   - First letter of last name (e.g., "s")
 #
 # Common formats:
 #   {first_name}-{last_name}              -> john-smith
 #   {first_name}.{last_name}              -> john.smith
 #   {first_name_initial}{last_name}       -> jsmith
 #   {last_name}{first_name_initial}       -> smithj
 #   {first_name_initial}{last_name_initial} -> js
 #   {first_name_initial}.{last_name}      -> j.smith
 #
 # Note: Spaces and hyphens in names are automatically removed (Jean-Paul -> jeanpaul)
 $USERNAME_FORMAT = (getenv('USERNAME_FORMAT') ? getenv('USERNAME_FORMAT') : '{first_name}-{last_name}');
 $USERNAME_REGEX  = (getenv('USERNAME_REGEX')  ? getenv('USERNAME_REGEX') : '^[\p{L}\p{N}_.-]{2,64}$');   #We use the username regex for groups too. Supports Unicode characters.

 if (getenv('PASSWORD_HASH')) { $PASSWORD_HASH = strtoupper(getenv('PASSWORD_HASH')); }
 $ACCEPT_WEAK_PASSWORDS = ((strcasecmp(getenv('ACCEPT_WEAK_PASSWORDS'),'TRUE') == 0) ? TRUE : FALSE);

 $min_uid = 2000;
 $min_gid = 2000;


 #Default attributes and objectclasses

 $LDAP['account_attribute'] = (getenv('LDAP_ACCOUNT_ATTRIBUTE') ? getenv('LDAP_ACCOUNT_ATTRIBUTE') : 'uid');
 $LDAP['account_objectclasses'] = array( 'person', 'inetOrgPerson', 'posixAccount' );
 $LDAP['default_attribute_map'] = array(
    "givenname" => array(
        "label" => "First name",
        "onkeyup" => "update_username(); update_email(); update_cn(); update_homedir(); check_email_validity(document.getElementById('mail').value);",
        "required" => TRUE,
    ),
    "sn" => array(
        "label" => "Last name",
        "onkeyup" => "update_username(); update_email(); update_cn(); update_homedir(); check_email_validity(document.getElementById('mail').value);",
        "required" => TRUE,
    ),
    "uid" => array(
        "label" => "System username",
        "onkeyup" => "check_entity_name_validity(document.getElementById('uid').value,'uid_div'); update_email(); update_homedir(); check_email_validity(document.getElementById('mail').value);",
    ),
    "cn" => array(
        "label" => "Common name",
        "onkeyup" => "auto_cn_update = false;",
    ),
    "mail" => array(
        "label" => "Email",
        "onkeyup" => "auto_email_update = false; check_email_validity(document.getElementById('mail').value);",
    )
 );

 $LDAP['group_attribute'] = (getenv('LDAP_GROUP_ATTRIBUTE') ? getenv('LDAP_GROUP_ATTRIBUTE') : 'cn');
 $LDAP['group_objectclasses'] = array( 'top', 'posixGroup' ); #groupOfUniqueNames is added automatically if rfc2307bis is available.

 $LDAP['default_group_attribute_map'] = array( "description" => array("label" => "Description"));

 $SHOW_POSIX_ATTRIBUTES = ((strcasecmp(getenv('SHOW_POSIX_ATTRIBUTES'),'TRUE') == 0) ? TRUE : FALSE);

 if ($SHOW_POSIX_ATTRIBUTES != TRUE) {
   if ($LDAP['account_attribute'] == "uid") {
     unset($LDAP['default_attribute_map']['cn']);
   }
   else {
     unset($LDAP['default_attribute_map']['uid']);
   }
 }
 else {
   $LDAP['default_attribute_map']["uidnumber"]  = array("label" => "UID");
   $LDAP['default_attribute_map']["gidnumber"]  = array("label" => "GID");
   $LDAP['default_attribute_map']["homedirectory"]  = array("label" => "Home directory", "onkeyup" => "auto_homedir_update = false;");
   $LDAP['default_attribute_map']["loginshell"]  = array("label" => "Shell", "default" => $DEFAULT_USER_SHELL);
   $LDAP['default_group_attribute_map']["gidnumber"] = array("label" => "Group ID number");
 }


 ## LDAP server

 $LDAP['uri'] = getenv('LDAP_URI');
 $LDAP['base_dn'] = getenv('LDAP_BASE_DN');
 $LDAP['admin_bind_dn'] = getenv('LDAP_ADMIN_BIND_DN');
 $LDAP['admin_bind_pwd'] = getenv('LDAP_ADMIN_BIND_PWD');
 $LDAP['connection_type'] = "plain";
 $LDAP['require_starttls'] = ((strcasecmp(getenv('LDAP_REQUIRE_STARTTLS'),'TRUE') == 0) ? TRUE : FALSE);
 $LDAP['ignore_cert_errors'] = ((strcasecmp(getenv('LDAP_IGNORE_CERT_ERRORS'),'TRUE') == 0) ? TRUE : FALSE);
 $LDAP['rfc2307bis_check_run'] = FALSE;


 # Various advanced LDAP settings

 $LDAP['admins_group'] = getenv('LDAP_ADMINS_GROUP');
 $LDAP['group_ou'] = (getenv('LDAP_GROUP_OU') ? getenv('LDAP_GROUP_OU') : 'groups');
 $LDAP['user_ou'] = (getenv('LDAP_USER_OU') ? getenv('LDAP_USER_OU') : 'people');
 $LDAP['forced_rfc2307bis'] = ((strcasecmp(getenv('FORCE_RFC2307BIS'),'TRUE') == 0) ? TRUE : FALSE);

 if (getenv('LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES')) { $account_additional_objectclasses = strtolower(getenv('LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES')); }
 if (getenv('LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES')) { $LDAP['account_additional_attributes'] = getenv('LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES'); }

 if (getenv('LDAP_GROUP_ADDITIONAL_OBJECTCLASSES')) { $group_additional_objectclasses = getenv('LDAP_GROUP_ADDITIONAL_OBJECTCLASSES'); }
 if (getenv('LDAP_GROUP_ADDITIONAL_ATTRIBUTES')) { $LDAP['group_additional_attributes'] = getenv('LDAP_GROUP_ADDITIONAL_ATTRIBUTES'); }

 if (getenv('LDAP_GROUP_MEMBERSHIP_ATTRIBUTE')) { $LDAP['group_membership_attribute'] = getenv('LDAP_GROUP_MEMBERSHIP_ATTRIBUTE'); }
 if (getenv('LDAP_GROUP_MEMBERSHIP_USES_UID')) {
   if (strtoupper(getenv('LDAP_GROUP_MEMBERSHIP_USES_UID')) == 'TRUE' )   { $LDAP['group_membership_uses_uid']  = TRUE;  }
   if (strtoupper(getenv('LDAP_GROUP_MEMBERSHIP_USES_UID')) == 'FALSE' )  { $LDAP['group_membership_uses_uid']  = FALSE; }
 }

 $LDAP['group_dn'] = "ou={$LDAP['group_ou']},{$LDAP['base_dn']}";
 $LDAP['user_dn'] = "ou={$LDAP['user_ou']},{$LDAP['base_dn']}";

 if (isset($account_additional_objectclasses) and $account_additional_objectclasses != "") {
   $LDAP['account_objectclasses'] = array_merge($LDAP['account_objectclasses'], explode(",", $account_additional_objectclasses));
 }
 if (isset($group_additional_objectclasses) and $group_additional_objectclasses != "") {
   $LDAP['group_objectclasses'] = array_merge($LDAP['group_objectclasses'], explode(",", $group_additional_objectclasses));
 }

 # Interface customisation

 $ORGANISATION_NAME = (getenv('ORGANISATION_NAME') ? getenv('ORGANISATION_NAME') : 'LDAP');
 $SITE_NAME = (getenv('SITE_NAME') ? getenv('SITE_NAME') : "$ORGANISATION_NAME user manager");

 $SITE_LOGIN_LDAP_ATTRIBUTE = (getenv('SITE_LOGIN_LDAP_ATTRIBUTE') ? getenv('SITE_LOGIN_LDAP_ATTRIBUTE') : $LDAP['account_attribute'] );
 $SITE_LOGIN_FIELD_LABEL = (getenv('SITE_LOGIN_FIELD_LABEL') ? getenv('SITE_LOGIN_FIELD_LABEL') : "Username" );
 
 $SERVER_HOSTNAME = (getenv('SERVER_HOSTNAME') ? getenv('SERVER_HOSTNAME') : "ldapusermanager.org");
 $SERVER_PATH = (getenv('SERVER_PATH') ? getenv('SERVER_PATH') : "/");

 $SESSION_TIMEOUT = (getenv('SESSION_TIMEOUT') ? getenv('SESSION_TIMEOUT') : 10);

 $NO_HTTPS = ((strcasecmp(getenv('NO_HTTPS'),'TRUE') == 0) ? TRUE : FALSE);

 $REMOTE_HTTP_HEADERS_LOGIN = ((strcasecmp(getenv('REMOTE_HTTP_HEADERS_LOGIN'),'TRUE') == 0) ? TRUE : FALSE);

 # Sending email

 $SMTP['host'] = getenv('SMTP_HOSTNAME');
 $SMTP['user'] = (getenv('SMTP_USERNAME') ? getenv('SMTP_USERNAME') : NULL);
 $SMTP['pass'] = (getenv('SMTP_PASSWORD') ? getenv('SMTP_PASSWORD') : NULL);
 $SMTP['port'] = (getenv('SMTP_HOST_PORT') ? getenv('SMTP_HOST_PORT') : 25);
 $SMTP['helo'] = (getenv('SMTP_HELO_HOST') ? getenv('SMTP_HELO_HOST') : NULL);
 $SMTP['ssl']  = ((strcasecmp(getenv('SMTP_USE_SSL'),'TRUE') == 0) ? TRUE : FALSE);
 $SMTP['tls']  = ((strcasecmp(getenv('SMTP_USE_TLS'),'TRUE') == 0) ? TRUE : FALSE);
 if ($SMTP['tls'] == TRUE) { $SMTP['ssl'] = FALSE; }

 $EMAIL_DOMAIN = (getenv('EMAIL_DOMAIN') ? getenv('EMAIL_DOMAIN') : Null);

 $default_email_from_domain = ($EMAIL_DOMAIN ? $EMAIL_DOMAIN : 'ldapusermanger.org');

 $EMAIL['from_address'] = (getenv('EMAIL_FROM_ADDRESS') ? getenv('EMAIL_FROM_ADDRESS') : "admin@" . $default_email_from_domain );
 $EMAIL['from_name'] = (getenv('EMAIL_FROM_NAME') ? getenv('EMAIL_FROM_NAME') : $SITE_NAME );

 if ($SMTP['host'] != "") { $EMAIL_SENDING_ENABLED = TRUE; } else { $EMAIL_SENDING_ENABLED = FALSE; }

 # Account requests

 $ACCOUNT_REQUESTS_ENABLED = ((strcasecmp(getenv('ACCOUNT_REQUESTS_ENABLED'),'TRUE') == 0) ? TRUE : FALSE);
 if (($EMAIL_SENDING_ENABLED == FALSE) && ($ACCOUNT_REQUESTS_ENABLED == TRUE)) {
   $ACCOUNT_REQUESTS_ENABLED = FALSE;
   error_log("$log_prefix Config: ACCOUNT_REQUESTS_ENABLED was set to TRUE but SMTP_HOSTNAME wasn't set, so account requesting has been disabled as we can't send out the request email",0);
 }

 $ACCOUNT_REQUESTS_EMAIL = (getenv('ACCOUNT_REQUESTS_EMAIL') ? getenv('ACCOUNT_REQUESTS_EMAIL') : $EMAIL['from_address']);


 # MFA/TOTP Configuration

 $MFA_ENABLED = ((strcasecmp(getenv('MFA_ENABLED'),'TRUE') == 0) ? TRUE : FALSE);
 $MFA_REQUIRED_GROUPS = (getenv('MFA_REQUIRED_GROUPS') ? explode(',', getenv('MFA_REQUIRED_GROUPS')) : array());
 $MFA_GRACE_PERIOD_DAYS = (getenv('MFA_GRACE_PERIOD_DAYS') ? intval(getenv('MFA_GRACE_PERIOD_DAYS')) : 7);
 $MFA_TOTP_ISSUER = (getenv('MFA_TOTP_ISSUER') ? getenv('MFA_TOTP_ISSUER') : $ORGANISATION_NAME);

 # MFA Schema Validation
 # We check this dynamically rather than relying on environment variables
 # because environment variables set during entrypoint don't propagate to Apache worker processes
 $MFA_SCHEMA_OK = FALSE;
 if ($MFA_ENABLED == TRUE) {
   // We'll check this when needed to avoid unnecessary LDAP queries on every page load
   // The check is performed in pages that need it (home page, MFA management, etc.)
   $MFA_SCHEMA_OK = NULL; // NULL means "not yet checked"
 }

 # MFA is only fully operational if enabled AND schema is valid
 # This will be determined dynamically when needed
 $MFA_FULLY_OPERATIONAL = NULL; // Will be set when schema check is performed

 # TOTP LDAP Attribute Names (configurable to match your LDAP schema)
 $TOTP_ATTRS = array(
   'secret' => (getenv('TOTP_SECRET_ATTRIBUTE') ? getenv('TOTP_SECRET_ATTRIBUTE') : 'totpSecret'),
   'status' => (getenv('TOTP_STATUS_ATTRIBUTE') ? getenv('TOTP_STATUS_ATTRIBUTE') : 'totpStatus'),
   'enrolled_date' => (getenv('TOTP_ENROLLED_DATE_ATTRIBUTE') ? getenv('TOTP_ENROLLED_DATE_ATTRIBUTE') : 'totpEnrolledDate'),
   'scratch_codes' => (getenv('TOTP_SCRATCH_CODES_ATTRIBUTE') ? getenv('TOTP_SCRATCH_CODES_ATTRIBUTE') : 'totpScratchCode'),
   'objectclass' => (getenv('TOTP_OBJECTCLASS') ? getenv('TOTP_OBJECTCLASS') : 'totpUser')
 );

 # Debugging

 $LDAP_DEBUG = ((strcasecmp(getenv('LDAP_DEBUG'),'TRUE') == 0) ? TRUE : FALSE);
 $LDAP_VERBOSE_CONNECTION_LOGS = ((strcasecmp(getenv('LDAP_VERBOSE_CONNECTION_LOGS'),'TRUE') == 0) ? TRUE : FALSE);
 $SESSION_DEBUG = ((strcasecmp(getenv('SESSION_DEBUG'),'TRUE') == 0) ? TRUE : FALSE);
 $SMTP['debug_level'] = getenv('SMTP_LOG_LEVEL');
 if (!is_numeric($SMTP['debug_level']) or $SMTP['debug_level'] >4 or $SMTP['debug_level'] <0) { $SMTP['debug_level'] = 0; }

 # Sanity checking

 $CUSTOM_LOGO = (getenv('CUSTOM_LOGO') ? getenv('CUSTOM_LOGO') : FALSE);
 $CUSTOM_STYLES = (getenv('CUSTOM_STYLES') ? getenv('CUSTOM_STYLES') : FALSE);

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
