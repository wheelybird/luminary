<?php

 #Modules and how they can be accessed.

 #access:
 #auth = need to be logged-in to see it
 #hidden_on_login = only visible when not logged in
 #admin = need to be logged in as an admin to see it

 $MODULES = array(
                    'log_in'          => 'hidden_on_login',
                    'home'            => 'auth',
                    'user_profile'    => 'auth',
                    'change_password' => 'auth',
                    'account_manager' => 'admin',
                    'system_config'   => 'admin',
                  );

 #Module display names (optional - if not set, directory name is used)
 $MODULE_NAMES = array(
                    'log_in'          => 'Log In',
                    'home'            => 'Home',
                    'user_profile'    => 'My Profile',
                    'change_password' => 'Change Password',
                    'account_manager' => 'Account Manager',
                    'system_config'   => 'System Config',
                    'log_out'         => 'Log Out',
                    'request_account' => 'Request Account',
                    'manage_mfa'      => 'Manage MFA',
                  );

if ($MFA_ENABLED == TRUE) {
  $MODULES['manage_mfa'] = 'auth';
}

if ($ACCOUNT_REQUESTS_ENABLED == TRUE) {
  $MODULES['request_account'] = 'hidden_on_login';
}
if (!$REMOTE_HTTP_HEADERS_LOGIN) {
  $MODULES['log_out'] = 'auth';
}

?>
