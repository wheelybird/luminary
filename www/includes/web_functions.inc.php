<?php
#Security level vars

$VALIDATED = FALSE;
$IS_ADMIN = FALSE;
$IS_SETUP_ADMIN = FALSE;
$ACCESS_LEVEL_NAME = array('account','admin');
unset($USER_ID);
$CURRENT_PAGE=htmlentities($_SERVER['PHP_SELF']);
$SENT_HEADERS = FALSE;
$SESSION_TIMED_OUT = FALSE;

$paths=explode('/',getcwd());
$THIS_MODULE=end($paths);

$GOOD_ICON = "&#9745;";
$WARN_ICON = "&#9888;";
$FAIL_ICON = "&#9940;";

$JS_EMAIL_REGEX='/^[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,}$/u;';

######################################################
# ERROR HANDLING - Set up custom error/exception handlers
######################################################

/**
 * Custom error handler - logs detailed errors but shows generic message to user
 */
function custom_error_handler($errno, $errstr, $errfile, $errline) {
  // Don't handle suppressed errors (@-operator)
  if (!(error_reporting() & $errno)) {
    return false;
  }

  // Map error types to readable names
  $error_types = array(
    E_ERROR => 'Error',
    E_WARNING => 'Warning',
    E_PARSE => 'Parse Error',
    E_NOTICE => 'Notice',
    E_CORE_ERROR => 'Core Error',
    E_CORE_WARNING => 'Core Warning',
    E_COMPILE_ERROR => 'Compile Error',
    E_COMPILE_WARNING => 'Compile Warning',
    E_USER_ERROR => 'User Error',
    E_USER_WARNING => 'User Warning',
    E_USER_NOTICE => 'User Notice',
    E_RECOVERABLE_ERROR => 'Recoverable Error',
    E_DEPRECATED => 'Deprecated',
    E_USER_DEPRECATED => 'User Deprecated'
  );

  $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Unknown Error';

  // Log detailed error message to stderr (Docker logs)
  $log_message = "PHP $error_type: $errstr in $errfile on line $errline";
  @file_put_contents('php://stderr', date('[Y-m-d H:i:s] ') . $log_message . "\n", FILE_APPEND);

  // For fatal errors, show generic error page
  if ($errno == E_ERROR || $errno == E_USER_ERROR || $errno == E_RECOVERABLE_ERROR) {
    show_generic_error_page($log_message);
    exit(1);
  }

  // Don't execute PHP's internal error handler
  return true;
}

/**
 * Custom exception handler - logs exception details but shows generic message to user
 */
function custom_exception_handler($exception) {
  // Log detailed exception information to stderr (Docker logs)
  $log_message = "Uncaught Exception: " . $exception->getMessage() . "\n" .
                 "File: " . $exception->getFile() . " on line " . $exception->getLine() . "\n" .
                 "Stack trace:\n" . $exception->getTraceAsString();
  @file_put_contents('php://stderr', date('[Y-m-d H:i:s] ') . $log_message . "\n", FILE_APPEND);

  // Show generic error page to user
  show_generic_error_page($log_message);
  exit(1);
}

/**
 * Display a generic error page to the user
 *
 * @param string $error_details Optional error details to show in development mode
 */
function show_generic_error_page($error_details = '') {
  // Check if we should show error details (for development)
  $show_details = (strcasecmp(getenv('SHOW_ERROR_DETAILS'), 'TRUE') == 0);

  // Clear any output buffers
  if (ob_get_level()) {
    ob_clean();
  }

  // Send HTTP 500 status
  http_response_code(500);

  // Display generic error page
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>
    <div class="container mt-5">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
          <div class="card border-danger">
            <div class="card-header bg-danger text-white text-center">
              <h4>System Error</h4>
            </div>
            <div class="card-body">
              <p class="text-center">An unexpected error has occurred. Please try again later.</p>
              <p class="text-center text-muted">If this problem persists, please contact your system administrator.</p>

              <?php if ($show_details && !empty($error_details)): ?>
              <div class="alert alert-warning mt-3">
                <strong>Error Details (development mode):</strong>
                <pre class="mt-2 mb-0" style="font-size: 0.85em; max-height: 300px; overflow-y: auto;"><?php echo htmlspecialchars($error_details); ?></pre>
              </div>
              <?php endif; ?>

              <p class="text-center mt-4">
                <a href="<?php echo url('/'); ?>" class="btn btn-primary">Return to Home</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  </html>
  <?php
}

// Set custom error and exception handlers
set_error_handler('custom_error_handler');
set_exception_handler('custom_exception_handler');

// Configure PHP error reporting
// All errors are logged to stderr (captured by Docker logs)
// display_errors should be Off in production (set in php.ini)
// We handle errors through our custom handlers above which write to stderr
error_reporting(E_ALL);

// Ensure PHP errors also go to stderr for Docker logging
// This catches any errors not handled by our custom handler
ini_set('error_log', 'php://stderr');
ini_set('log_errors', '1');

######################################################

if (isset($_SERVER['HTTPS']) and
   ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) or
   isset($_SERVER['HTTP_X_FORWARDED_PROTO']) and
   $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $SITE_PROTOCOL = 'https://';
}
else {
  $SITE_PROTOCOL = 'http://';
}

include ("config.inc.php");    # get local settings
include ("modules.inc.php");   # module definitions

if (substr($SERVER_PATH, -1) != "/") { $SERVER_PATH .= "/"; }
$THIS_MODULE_PATH="{$SERVER_PATH}{$THIS_MODULE}";

$DEFAULT_COOKIE_OPTIONS = array( 'expires' => time()+(60 * $SESSION_TIMEOUT),
                                 'path' => $SERVER_PATH,
                                 'domain' => '',
                                 'secure' => $NO_HTTPS ? FALSE : TRUE,
                                 'samesite' => 'strict'
                               );

######################################################
# SESSION INITIALISATION
######################################################

// Define constant to prevent direct access to include files
if (!defined('LDAP_USER_MANAGER')) {
  define('LDAP_USER_MANAGER', true);
}

// Initialise session handler (LDAP-backed if USE_LDAP_AS_DB is enabled)
// This must be done before any session usage
// Include LDAP functions first so session handler can use them
include_once ("ldap_functions.inc.php");
include_once ("ldap_app_data_functions.inc.php");
include_once ("ldap_session_handler.inc.php");
ldap_session_init();

######################################################

if ($REMOTE_HTTP_HEADERS_LOGIN) {
  login_via_headers();
} else {
  validate_passkey_cookie();
}


######################################################

function generate_passkey() {

 $rnd1 = mt_rand(10000000, mt_getrandmax());
 $rnd2 = mt_rand(10000000, mt_getrandmax());
 $rnd3 = mt_rand(10000000, mt_getrandmax());
 return sprintf("%0x",$rnd1) . sprintf("%0x",$rnd2) . sprintf("%0x",$rnd3);

}


######################################################

/**
 * Generate URL with SERVER_PATH prefix
 *
 * @param string $path  URL path (with or without leading slash)
 * @return string       Full URL with SERVER_PATH prefix
 */
function url($path) {

  global $SERVER_PATH;

  // Ensure path starts with /
  if ($path[0] !== '/') {
    $path = '/' . $path;
  }

  // Remove SERVER_PATH if already present (avoid double-prefix)
  if (!empty($SERVER_PATH) && strpos($path, $SERVER_PATH) === 0) {
    $path = substr($path, strlen($SERVER_PATH));
  }

  return $SERVER_PATH . $path;

}


######################################################

function set_passkey_cookie($user_id,$is_admin) {

 # Create a random value, store it locally and set it in a cookie.

 global $SESSION_TIMEOUT, $VALIDATED, $USER_ID, $IS_ADMIN, $log_prefix, $SESSION_DEBUG, $DEFAULT_COOKIE_OPTIONS;


 $passkey = generate_passkey();
 $this_time=time();
 $admin_val = 0;

 if ($is_admin == TRUE ) {
  $admin_val = 1;
  $IS_ADMIN = TRUE;
 }
 $filename = preg_replace('/[^a-zA-Z0-9]/','_', $user_id);
 @ file_put_contents("/tmp/$filename","$passkey:$admin_val:$this_time");
 setcookie('orf_cookie', "$user_id:$passkey", $DEFAULT_COOKIE_OPTIONS);
 $sessto_cookie_opts = $DEFAULT_COOKIE_OPTIONS;
 $sessto_cookie_opts['expires'] = $this_time+7200;
 setcookie('sessto_cookie', $this_time+(60 * $SESSION_TIMEOUT), $sessto_cookie_opts);
 if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Session: user $user_id validated (IS_ADMIN={$IS_ADMIN}), sent orf_cookie to the browser.",0); }
 $VALIDATED = TRUE;

}


######################################################

function login_via_headers() {

  global $IS_ADMIN, $USER_ID, $VALIDATED, $LDAP;
  //['admins_group'];
  $USER_ID = $_SERVER['HTTP_REMOTE_USER'];
  $remote_groups = explode(',',$_SERVER['HTTP_REMOTE_GROUPS']);
  $IS_ADMIN = in_array($LDAP['admins_group'],$remote_groups);
  // users are always validated as we assume, that the auth server does this
  $VALIDATED = true;

}


######################################################

function validate_passkey_cookie() {

  global $SESSION_TIMEOUT, $IS_ADMIN, $USER_ID, $VALIDATED, $log_prefix, $SESSION_TIMED_OUT, $SESSION_DEBUG;

  $this_time=time();
  $VALIDATED = FALSE;
  $IS_ADMIN = FALSE;

  if (isset($_COOKIE['orf_cookie'])) {

    list($user_id,$c_passkey) = explode(":",$_COOKIE['orf_cookie']);
    $filename = preg_replace('/[^a-zA-Z0-9]/','_', $user_id);
    $session_file = @ file_get_contents("/tmp/$filename");
    if (!$session_file) {
      if ($SESSION_DEBUG == TRUE) {  error_log("$log_prefix Session: orf_cookie was sent by the client but the session file wasn't found at /tmp/$filename",0); }
    }
    else {
      list($f_passkey,$f_is_admin,$f_time) = explode(":",$session_file);
      if (!empty($c_passkey) and $f_passkey == $c_passkey and $this_time < $f_time+(60 * $SESSION_TIMEOUT)) {
        if ($f_is_admin == 1) { $IS_ADMIN = TRUE; }
        $VALIDATED = TRUE;
        $USER_ID=$user_id;
        if ($SESSION_DEBUG == TRUE) {  error_log("$log_prefix Setup session: Cookie and session file values match for user {$user_id} - VALIDATED (ADMIN = {$IS_ADMIN})",0); }
        set_passkey_cookie($USER_ID,$IS_ADMIN);
      }
      else {
        if ($SESSION_DEBUG == TRUE) {
          $this_error="$log_prefix Session: orf_cookie was sent by the client and the session file was found at /tmp/$filename, but";
          if (empty($c_passkey)) { $this_error .= " the cookie passkey wasn't set;"; }
          if ($c_passkey != $f_passkey) { $this_error .= " the session file passkey didn't match the cookie passkey;"; }
          $this_error.=" Cookie: {$_COOKIE['orf_cookie']} - Session file contents: $session_file";
          error_log($this_error,0);
        }
      }
    }

  }
  else {
    if ($SESSION_DEBUG == TRUE) { error_log("$log_prefix Session: orf_cookie wasn't sent by the client.",0); }
    if (isset($_COOKIE['sessto_cookie'])) {
      $this_session_timeout = $_COOKIE['sessto_cookie'];
      if ($this_time >= $this_session_timeout) {
        $SESSION_TIMED_OUT = TRUE;
        if ($SESSION_DEBUG == TRUE) { error_log("$log_prefix Session: The session had timed-out (over $SESSION_TIMEOUT mins idle).",0); }
      }
    }
  }

}


######################################################

function set_setup_cookie() {

 # Create a random value, store it locally and set it in a cookie.

 global $SESSION_TIMEOUT, $IS_SETUP_ADMIN, $log_prefix, $SESSION_DEBUG, $DEFAULT_COOKIE_OPTIONS;

 $passkey = generate_passkey();
 $this_time=time();

 $IS_SETUP_ADMIN = TRUE;

 @ file_put_contents("/tmp/ldap_setup","$passkey:$this_time");

 setcookie('setup_cookie', $passkey, $DEFAULT_COOKIE_OPTIONS);

 if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Setup session: sent setup_cookie to the client.",0); }

}


######################################################

function validate_setup_cookie() {

 global $SESSION_TIMEOUT, $IS_SETUP_ADMIN, $log_prefix, $SESSION_DEBUG;

 if (isset($_COOKIE['setup_cookie'])) {

  $c_passkey = $_COOKIE['setup_cookie'];
  if (file_exists("/tmp/ldap_setup")) {
   $session_file = file_get_contents("/tmp/ldap_setup");
  } else {
   $session_file = FALSE;
  }
  if (!$session_file) {
   $IS_SETUP_ADMIN = FALSE;
   if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Setup session: setup_cookie was sent by the client but the session file wasn't found at /tmp/ldap_setup",0); }
   return;
  }
  list($f_passkey,$f_time) = explode(":",$session_file);
  $this_time=time();
  if (!empty($c_passkey) and $f_passkey == $c_passkey and $this_time < $f_time+(60 * $SESSION_TIMEOUT)) {
   $IS_SETUP_ADMIN = TRUE;
   if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Setup session: Cookie and session file values match - VALIDATED ",0); }
   set_setup_cookie();
  }
  elseif ( $SESSION_DEBUG == TRUE) {
   $this_error="$log_prefix Setup session: setup_cookie was sent by the client and the session file was found at /tmp/ldap_setup, but";
   if (empty($c_passkey)) { $this_error .= " the cookie passkey wasn't set;"; }
   if ($c_passkey != $f_passkey) { $this_error .= " the session file passkey didn't match the cookie passkey;"; }
   $this_error += " Cookie: {$_COOKIE['setup_cookie']} - Session file contents: $session_file";
   error_log($this_error,0);
  }
 }
 elseif ( $SESSION_DEBUG == TRUE) {
   error_log("$log_prefix Session: setup_cookie wasn't sent by the client.",0);
 }

}


######################################################

function log_out($method='normal') {

 # Delete the passkey from the database and the passkey cookie

 global $USER_ID, $SERVER_PATH, $DEFAULT_COOKIE_OPTIONS;

 $this_time=time();

 $orf_cookie_opts = $DEFAULT_COOKIE_OPTIONS;
 $orf_cookie_opts['expires'] = $this_time-20000;
 $sessto_cookie_opts = $DEFAULT_COOKIE_OPTIONS;
 $sessto_cookie_opts['expires'] = $this_time-20000;

 setcookie('orf_cookie', "", $DEFAULT_COOKIE_OPTIONS);
 setcookie('sessto_cookie', "", $DEFAULT_COOKIE_OPTIONS);

 $filename = preg_replace('/[^a-zA-Z0-9]/','_', $USER_ID);
 @ unlink("/tmp/$filename");

 // Audit log logout
 if (function_exists('audit_log')) {
  $logout_method = ($method == 'auto') ? 'automatic (timeout)' : 'manual';
  audit_log('logout', $USER_ID, $logout_method, 'success', $USER_ID);
 }

 if ($method == 'auto') { $options = "?logged_out"; } else { $options = ""; }
 header("Location:  //{$_SERVER["HTTP_HOST"]}{$SERVER_PATH}index.php$options\n\n");

}


######################################################

function render_header($title="",$menu=TRUE) {

 global $SITE_NAME, $IS_ADMIN, $SENT_HEADERS, $SERVER_PATH, $CUSTOM_STYLES;

 if (empty($title)) { $title = $SITE_NAME; }

 #Initialise the HTML output for the page.

 ?>
<!DOCTYPE html>
<HTML>
<HEAD>
 <TITLE><?php print "$title"; ?></TITLE>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <link rel="stylesheet" href="<?php print url('/bootstrap/css/bootstrap.min.css'); ?>">
 <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
 <link rel="stylesheet" href="<?php print url('/custom.css'); ?>">
 <?php if ($CUSTOM_STYLES) echo '<link rel="stylesheet" href="'.$CUSTOM_STYLES.'">' ?>
 <script src="<?php print url('/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
</HEAD>
<BODY>
<?php

 if ($menu == TRUE) {
  render_menu();
 }

 if (isset($_GET['logged_in'])) {

  ?>
  <script>
    window.setTimeout(function() {
      const alert = document.querySelector('.alert');
      if (alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
      }
    }, 10000);
  </script>
  <?php

 }
 $SENT_HEADERS = TRUE;

}


######################################################

function render_menu() {

 #Render the navigation menu.
 #The menu is dynamically rendered the $MODULES hash

 global $SITE_NAME, $MODULES, $THIS_MODULE, $VALIDATED, $IS_ADMIN, $USER_ID, $SERVER_PATH, $CUSTOM_LOGO;

 ?>
  <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
   <div class="container-fluid">
     <?php if ($CUSTOM_LOGO) echo '<a class="navbar-brand" href="./"><img src="'.$CUSTOM_LOGO.'" class="logo" alt="logo"></a>' ?>
     <a class="navbar-brand" href="./"><?php print $SITE_NAME ?></a>
     <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
       <span class="navbar-toggler-icon"></span>
     </button>
     <div class="collapse navbar-collapse" id="navbarNav">
       <ul class="navbar-nav me-auto">
       <?php
       foreach ($MODULES as $module => $access) {

        // Use custom display name if defined, otherwise convert directory name
        global $MODULE_NAMES;
        if (isset($MODULE_NAMES[$module])) {
          $this_module_name = $MODULE_NAMES[$module];
        } else {
          $this_module_name = stripslashes(ucwords(preg_replace('/_/',' ',$module)));
        }

        $show_this_module = TRUE;
        if ($VALIDATED == TRUE) {
         if ($access == 'hidden_on_login') { $show_this_module = FALSE; }
         if ($IS_ADMIN == FALSE and $access == 'admin' ){ $show_this_module = FALSE; }
        }
        else {
         if ($access != 'hidden_on_login') { $show_this_module = FALSE; }
        }
        #print "<p>$module - access is $access & show is $show_this_module</p>";
        if ($show_this_module == TRUE ) {
         if ($module == $THIS_MODULE) {
          print "<li class='nav-item'><a class='nav-link active' href='" . url("/{$module}/") . "'>$this_module_name</a></li>\n";
         }
         else {
          print "<li class='nav-item'><a class='nav-link' href='" . url("/{$module}/") . "'>$this_module_name</a></li>\n";
         }
        }
       }
       ?>
       </ul>
       <ul class="navbar-nav">
        <li class="nav-item"><span class="nav-link"><?php if(isset($USER_ID)) { print $USER_ID; } ?></span></li>
       </ul>
     </div>
   </div>
  </nav>
 <?php
}


######################################################

function render_footer() {

#Finish rendering an HTML page.

?>
 </BODY>
</HTML>
<?php

}


######################################################

function check_password_expiry_restriction($current_module) {

 global $USER_ID, $PASSWORD_POLICY_ENABLED, $PPOLICY_ENABLED, $PASSWORD_EXPIRY_DAYS;
 global $SERVER_PATH, $log_prefix, $SESSION_DEBUG, $LDAP;

 // Only enforce if password policy and ppolicy are enabled, and expiry is configured
 if ($PASSWORD_POLICY_ENABLED != TRUE || $PPOLICY_ENABLED != TRUE || $PASSWORD_EXPIRY_DAYS <= 0) {
   return; // No password expiry restrictions
 }

 // Ensure password_policy_functions is loaded
 if (!function_exists('password_policy_is_expired')) {
   include_once "password_policy_functions.inc.php";
   if (!function_exists('password_policy_is_expired')) {
     // Can't check password expiry - allow access
     return;
   }
 }

 // Allow access to these modules regardless of password expiry status
 $allowed_modules = array('change_password', 'log_out', 'log_in');
 if (in_array($current_module, $allowed_modules)) {
   return;
 }

 // Check if we already have password expiry info in session
 $password_expired = isset($_SESSION['password_expired']) ? $_SESSION['password_expired'] : null;
 $days_remaining = isset($_SESSION['password_days_remaining']) ? $_SESSION['password_days_remaining'] : null;

 // If not in session or session data is stale (> 5 minutes), query LDAP
 $session_time = isset($_SESSION['password_check_time']) ? $_SESSION['password_check_time'] : 0;
 $cache_duration = 300; // 5 minutes

 if ($password_expired === null || (time() - $session_time) > $cache_duration) {
   // Connect to LDAP and check user's password expiry status
   $ldap_connection = open_ldap_connection();
   if (!$ldap_connection) {
     // Can't check password expiry - allow access
     return;
   }

   // Find user DN
   $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
     "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
     array('dn'));

   if ($user_search) {
     $user_entry = ldap_get_entries($ldap_connection, $user_search);

     if ($user_entry['count'] > 0) {
       $user_dn = $user_entry[0]['dn'];

       // Check if password is expired
       $days_remaining = null;
       $password_expired = password_policy_is_expired($ldap_connection, $user_dn, $days_remaining);
       $should_warn = password_policy_should_warn($ldap_connection, $user_dn, $days_remaining);

       // Store in session for caching
       $_SESSION['password_expired'] = $password_expired;
       $_SESSION['password_days_remaining'] = $days_remaining;
       $_SESSION['password_should_warn'] = $should_warn;
       $_SESSION['password_check_time'] = time();

       if ($SESSION_DEBUG == TRUE) {
         error_log("$log_prefix Password Expiry: User {$USER_ID} - expired={$password_expired}, days_remaining={$days_remaining}, should_warn={$should_warn}",0);
       }
     }
   }

   ldap_close($ldap_connection);
 }

 // If password is expired, force redirect to change_password
 if ($password_expired === true) {
   if ($SESSION_DEBUG == TRUE) {
     error_log("$log_prefix Password Expiry: User {$USER_ID} has expired password - redirecting to change_password",0);
   }
   header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}change_password?password_expired\n\n");
   exit(0);
 }
}


######################################################

function check_mfa_access_restriction($current_module) {

 global $USER_ID, $MFA_FEATURE_ENABLED, $SERVER_PATH, $log_prefix, $SESSION_DEBUG;
 global $LDAP, $TOTP_ATTRS, $MFA_REQUIRED_GROUPS;

 // Only enforce if MFA features are operational
 if ($MFA_FEATURE_ENABLED != TRUE) {
   return; // No MFA restrictions
 }

 // Ensure totp_functions is loaded
 if (!function_exists('totp_user_requires_mfa')) {
   // Can't check MFA requirements without totp_functions - allow access
   return;
 }

 // Allow access to these modules regardless of MFA status
 $allowed_modules = array('manage_mfa', 'log_out', 'log_in');
 if (in_array($current_module, $allowed_modules)) {
   return;
 }

 // Connect to LDAP and check user's MFA status
 $ldap_connection = open_ldap_connection();
 if (!$ldap_connection) {
   // Can't check MFA status - allow access
   return;
 }

 $status_attr = $TOTP_ATTRS['status'];
 $status_attr_lower = strtolower($status_attr);

 $user_search = ldap_search($ldap_connection, $LDAP['user_dn'],
   "({$LDAP['account_attribute']}=" . ldap_escape($USER_ID, "", LDAP_ESCAPE_FILTER) . ")",
   array($status_attr, 'memberOf'));

 if ($user_search) {
   $user_entry = ldap_get_entries($ldap_connection, $user_search);

   if ($user_entry['count'] > 0) {
     $totp_status = isset($user_entry[0][$status_attr_lower][0]) ? $user_entry[0][$status_attr_lower][0] : 'none';

     // If user doesn't have active MFA, check if it's required
     if ($totp_status !== 'active') {
       // Check if user requires MFA (is in an MFA-required group)
       $mfa_result = totp_user_requires_mfa($ldap_connection, $USER_ID, $MFA_REQUIRED_GROUPS);
       ldap_close($ldap_connection);

       if ($mfa_result['required']) {
         // User requires MFA but doesn't have active status - redirect to MFA setup
         if ($SESSION_DEBUG == TRUE) {
           error_log("$log_prefix MFA: User {$USER_ID} requires MFA but status is '{$totp_status}' - redirecting to MFA setup",0);
         }
         header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}manage_mfa?mfa_required\n\n");
         exit(0);
       }
       // else: User doesn't require MFA, allow access
       return;
     }
     // else: User has active MFA, allow access
   }
 }

 ldap_close($ldap_connection);
}

######################################################

function set_page_access($level) {

 global $IS_ADMIN, $IS_SETUP_ADMIN, $VALIDATED, $log_prefix, $SESSION_DEBUG, $SESSION_TIMED_OUT, $SERVER_PATH;

 #Set the security level needed to view a page.
 #This should be one of the first pieces of code
 #you call on a page.
 #Either 'setup', 'admin' or 'user'.

 if ($level == "setup") {
  if ($IS_SETUP_ADMIN == TRUE) {
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "{$SERVER_PATH}setup/index.php?unauthorised\n\n");
   if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Session: UNAUTHORISED: page security level is 'setup' but IS_SETUP_ADMIN isn't TRUE",0); }
   exit(0);
  }
 }

 if ($SESSION_TIMED_OUT == TRUE) { $reason = "session_timeout"; } else { $reason = "unauthorised"; }

 if ($level == "admin") {
  if ($IS_ADMIN == TRUE and $VALIDATED == TRUE) {
   // Check MFA access restrictions if LOGIN_REQUIRES_MFA is enabled
   global $THIS_MODULE;
   check_mfa_access_restriction($THIS_MODULE);
   // Check password expiry restrictions
   check_password_expiry_restriction($THIS_MODULE);
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "{$SERVER_PATH}log_in/index.php?$reason&redirect_to=" . base64_encode($_SERVER['REQUEST_URI']) . "\n\n");
   if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Session: no access to page ($reason): page security level is 'admin' but IS_ADMIN = '{$IS_ADMIN}' and VALIDATED = '{$VALIDATED}' (user) ",0); }
   exit(0);
  }
 }

 if ($level == "user") {
  if ($VALIDATED == TRUE){
   // Check MFA access restrictions if LOGIN_REQUIRES_MFA is enabled
   global $THIS_MODULE;
   check_mfa_access_restriction($THIS_MODULE);
   // Check password expiry restrictions
   check_password_expiry_restriction($THIS_MODULE);
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "{$SERVER_PATH}log_in/index.php?$reason&redirect_to=" . base64_encode($_SERVER['REQUEST_URI']) . "\n\n");
   if ( $SESSION_DEBUG == TRUE) {  error_log("$log_prefix Session: no access to page ($reason): page security level is 'user' but VALIDATED = '{$VALIDATED}'",0); }
   exit(0);
  }
 }

}


######################################################

function is_valid_email($email) {

 // Support internationalised email addresses per RFC 6530-6533
 return (!filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE)) ? FALSE : TRUE;

}


######################################################

function render_js_username_check(){

 global $USERNAME_REGEX, $ENFORCE_USERNAME_VALIDATION;

 if ($ENFORCE_USERNAME_VALIDATION == TRUE) {

 print <<<EoCheckJS
<script>

 function check_entity_name_validity(name,div_id) {

  var check_regex = /$USERNAME_REGEX/u;
  var element = document.getElementById(div_id);

  // Add null check to prevent errors (fixes #214)
  if (!element) return;

  if (! check_regex.test(name) ) {
   element.classList.add("is-invalid");
   element.classList.remove("is-valid");
  }
  else {
   element.classList.remove("is-invalid");
   element.classList.add("is-valid");
  }

 }

</script>

EoCheckJS;
 }
 else {
  print "<script> function check_entity_name_validity(name,div_id) {} </script>";
 }

}


######################################################

function remove_accents($str) {
  // Handle special multi-character ligatures and letters
  $replacements = array(
    'æ' => 'ae', 'Æ' => 'AE',
    'ø' => 'o',  'Ø' => 'O',
    'å' => 'a',  'Å' => 'A',
    'ß' => 'ss',
    'œ' => 'oe', 'Œ' => 'OE',
    'đ' => 'd',  'Đ' => 'D',
    'ð' => 'd',  'Ð' => 'D',
    'þ' => 'th', 'Þ' => 'TH',
  );
  $str = strtr($str, $replacements);

  // Normalise to NFD (decomposed form) and remove combining diacritical marks
  if (class_exists('Normalizer', false)) {
    $str = Normalizer::normalize($str, Normalizer::FORM_D);
    // Remove combining diacritical marks (U+0300 to U+036F)
    $str = preg_replace('/\p{Mn}/u', '', $str);
  } else {
    // Fallback: use iconv if Normalizer is not available
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
  }

  return $str;
}


######################################################

/**
 * Generate a username from first and last name using the configured USERNAME_FORMAT.
 *
 * Supported template variables:
 *   {first_name}          - Full first name
 *   {first_name_initial}  - First letter of first name
 *   {last_name}           - Full last name
 *   {last_name_initial}   - First letter of last name
 *
 * Examples:
 *   {first_name_initial}{last_name}       -> jsmith (for John Smith)
 *   {last_name}{first_name_initial}       -> smithj
 *   {first_name_initial}{last_name_initial} -> js
 *   {first_name}.{last_name}              -> john.smith
 *
 * Note: Automatically removes spaces and hyphens from compound names (Jean-Paul -> jeanpaul)
 *       ALWAYS removes accents for POSIX compatibility (Hæppy Testør -> haeppy testor)
 *       This ensures usernames work with home directories, email addresses, and LDAP
 *
 * @param string $fn First name
 * @param string $ln Last name
 * @return string Generated username (ASCII-safe for POSIX/LDAP compliance)
 */
function generate_username($fn,$ln) {

  global $USERNAME_FORMAT;

  // Handle multiple first names with hyphens/spaces (fixes #186)
  // Remove spaces and hyphens from names for username generation
  $fn_clean = str_replace(array(' ', '-'), '', $fn);
  $ln_clean = str_replace(array(' ', '-'), '', $ln);

  // ALWAYS remove accents/diacritics for POSIX/LDAP compatibility
  // Required for: homeDirectory (RFC 2307 uses IA5String), email addresses, filesystem paths
  // This matches the JavaScript behaviour: .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  $fn_clean = remove_accents($fn_clean);
  $ln_clean = remove_accents($ln_clean);

  $username = $USERNAME_FORMAT;
  $username = str_replace('{first_name}',strtolower($fn_clean), $username);
  $username = str_replace('{first_name_initial}',strtolower(mb_substr($fn_clean, 0, 1)), $username);
  $username = str_replace('{last_name}',strtolower($ln_clean), $username);
  $username = str_replace('{last_name_initial}',strtolower(mb_substr($ln_clean, 0, 1)), $username);

  return $username;

}


######################################################

function render_js_username_generator($firstname_field_id,$lastname_field_id,$username_field_id,$username_div_id) {

 #Parameters are the IDs of the input fields and username name div in the account creation form.
 #The div will be set to warning if the username is invalid.

 global $USERNAME_FORMAT;

  print <<<EoRenderJS

<script>
 function update_username() {

  var first_name_elem = document.getElementById('$firstname_field_id');
  var last_name_elem  = document.getElementById('$lastname_field_id');
  var username_elem   = document.getElementById('$username_field_id');

  // Add null checks to prevent errors (fixes #214)
  if (!first_name_elem || !last_name_elem || !username_elem) return;

  var first_name = first_name_elem.value;
  var last_name  = last_name_elem.value;

  // Don't generate username if both names are empty or whitespace-only
  if (!first_name.trim() && !last_name.trim()) {
    return;
  }

  // Clean names to match PHP backend: remove spaces and hyphens (fixes #186)
  var first_name_clean = first_name.replace(/[\s-]/g, '');
  var last_name_clean = last_name.replace(/[\s-]/g, '');

  // ALWAYS remove accents for POSIX/LDAP compatibility (matches PHP remove_accents())
  // First, handle special multi-character ligatures and letters (must be done before NFD)
  var replacements = {
    'æ': 'ae', 'Æ': 'AE',
    'ø': 'o',  'Ø': 'O',
    'å': 'a',  'Å': 'A',
    'ß': 'ss',
    'œ': 'oe', 'Œ': 'OE',
    'đ': 'd',  'Đ': 'D',
    'ð': 'd',  'Ð': 'D',
    'þ': 'th', 'Þ': 'TH'
  };

  for (var char in replacements) {
    first_name_clean = first_name_clean.replace(new RegExp(char, 'g'), replacements[char]);
    last_name_clean = last_name_clean.replace(new RegExp(char, 'g'), replacements[char]);
  }

  // Then normalise to NFD and remove combining diacritical marks
  first_name_clean = first_name_clean.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  last_name_clean = last_name_clean.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  var template = '$USERNAME_FORMAT';
  var actual_username = template;

  actual_username = actual_username.replace('{first_name}', first_name_clean.toLowerCase());
  actual_username = actual_username.replace('{first_name_initial}', first_name_clean.charAt(0).toLowerCase());
  actual_username = actual_username.replace('{last_name}', last_name_clean.toLowerCase());
  actual_username = actual_username.replace('{last_name_initial}', last_name_clean.charAt(0).toLowerCase());

  check_entity_name_validity(actual_username,'$username_div_id');

  username_elem.value = actual_username;

 }

</script>

EoRenderJS;

}


######################################################

function render_js_cn_generator($firstname_field_id,$lastname_field_id,$cn_field_id,$cn_div_id) {

  global $ENFORCE_USERNAME_VALIDATION;

  if ($ENFORCE_USERNAME_VALIDATION == TRUE) {
    $gen_js = "first_name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') + last_name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')";
  }
  else {
    $gen_js = "first_name + ' ' + last_name";
  }

  print <<<EoRenderCNJS
<script>

 var auto_cn_update = true;

 function update_cn() {

  if ( auto_cn_update == true ) {
    var first_name_elem = document.getElementById('$firstname_field_id');
    var last_name_elem = document.getElementById('$lastname_field_id');
    var cn_elem = document.getElementById('$cn_field_id');

    // Add null checks to prevent errors
    if (!first_name_elem || !last_name_elem || !cn_elem) return;

    var first_name = first_name_elem.value;
    var last_name  = last_name_elem.value;

    // Don't generate CN if both names are empty or whitespace-only
    if (!first_name.trim() && !last_name.trim()) return;

    this_cn = $gen_js;

    check_entity_name_validity(this_cn,'$cn_div_id');

    cn_elem.value = this_cn;
  }

 }
</script>

EoRenderCNJS;

}


######################################################

function render_js_email_generator($username_field_id,$email_field_id) {

 global $EMAIL_DOMAIN;

  print <<<EoRenderEmailJS
<script>

 var auto_email_update = true;

 function update_email() {

  if ( auto_email_update == true && "$EMAIL_DOMAIN" != ""  ) {
    var username_elem = document.getElementById('$username_field_id');
    var email_elem = document.getElementById('$email_field_id');

    // Add null checks to prevent errors
    if (!username_elem || !email_elem) return;

    var username = username_elem.value;

    // Don't generate email if username is empty or whitespace-only
    if (!username.trim()) return;

    email_elem.value = username + '@' + "$EMAIL_DOMAIN";
  }

 }
</script>

EoRenderEmailJS;

}


######################################################

function render_js_homedir_generator($username_field_id,$homedir_field_id) {

  print <<<EoRenderHomedirJS
<script>

 var auto_homedir_update = true;

 function update_homedir() {

  if ( auto_homedir_update == true ) {
    var username_elem = document.getElementById('$username_field_id');
    var homedir_elem = document.getElementById('$homedir_field_id');

    // Add null checks to prevent errors
    if (!username_elem || !homedir_elem) return;

    var username = username_elem.value;

    // Don't generate homedir if username is empty or whitespace-only
    if (!username.trim()) return;

    homedir_elem.value = "/home/" + username;
  }

 }
</script>

EoRenderHomedirJS;

}

######################################################

function render_dynamic_field_js() {

?>
<script>

  function add_field_to(attribute_name,value=null) {

    var parent      = document.getElementById(attribute_name + '_input_div');
    var input_div   = document.createElement('div');

    window[attribute_name + '_count'] = (window[attribute_name + '_count'] === undefined) ? 1 : window[attribute_name + '_count'] + 1;
    var input_field_id = attribute_name + window[attribute_name + '_count'];
    var input_div_id = 'div' + '_' + input_field_id;

    input_div.className = 'input-group';
    input_div.id = input_div_id;

    parent.appendChild(input_div);

    var input_field = document.createElement('input');
        input_field.type = 'text';
        input_field.className = 'form-control';
        input_field.id = input_field_id;
        input_field.name = attribute_name + '[]';
        input_field.value = value;

    var remove_button = document.createElement('button');
        remove_button.type = 'button';
        remove_button.className = 'btn btn-secondary';
        remove_button.onclick = function() { var div_to_remove = document.getElementById(input_div_id); div_to_remove.innerHTML = ""; }
        remove_button.innerHTML = '-';

    input_div.appendChild(input_field);
    input_div.appendChild(remove_button);

  }

</script>
<?php

}


######################################################

function render_attribute_fields($attribute,$label,$values_r,$resource_identifier,$onkeyup="",$inputtype="",$tabindex=null) {

  global $THIS_MODULE_PATH, $LDAP;

  // Check if this is the account identifier field
  $is_account_identifier = ($attribute == $LDAP['account_attribute']);

  // Add special styling for account identifier
  $field_class = 'form-control';
  $label_class = 'col-sm-3 col-form-label text-end';
  if ($is_account_identifier) {
    $field_class .= ' bg-light border-primary';
    $label_class .= ' fw-bold';
  }

  ?>

     <div class="row mb-3" id="<?php print $attribute; ?>_div">

       <label for="<?php print $attribute; ?>" class="<?php print $label_class; ?>"><?php if ($is_account_identifier) { ?><i class="bi bi-key-fill text-primary"></i> <?php } ?><?php print $label; ?></label>
       <div class="col-sm-6" id="<?php print $attribute; ?>_input_div">
       <?php if($inputtype == "multipleinput") {
             ?><div class="input-group">
                  <input type="text" class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>[]" value="<?php if (isset($values_r[0])) { print htmlspecialchars(decode_ldap_value($values_r[0]), ENT_QUOTES, 'UTF-8'); } ?>">
                  <button type="button" class="btn btn-secondary" onclick="add_field_to('<?php print $attribute; ?>')">+</button>
              </div>
            <?php
               if (isset($values_r['count']) and $values_r['count'] > 0) {
                 unset($values_r['count']);
                 $remaining_values = array_slice($values_r, 1);
                 print "<script>";
                 foreach($remaining_values as $this_value) {
                   $decoded_value = decode_ldap_value($this_value);
                   $escaped_value = htmlspecialchars($decoded_value, ENT_QUOTES, 'UTF-8');
                   print "add_field_to('$attribute','$escaped_value');";
                 }
                 print "</script>";
               }
             }
             elseif ($inputtype == "binary") {
               $button_text="Browse";
               $file_button_action="disabled";
               $description="Select a file to upload";
               $mimetype="";

               if (isset($values_r[0])) {
                 $this_file_info = new finfo(FILEINFO_MIME_TYPE);
                 $mimetype = $this_file_info->buffer($values_r[0]);
                 if (strlen($mimetype) > 23) { $mimetype = substr($mimetype,0,19) . "..."; }
                 $description="Download $mimetype file (" . human_readable_filesize(strlen($values_r[0])) . ")";
                 $button_text="Replace file";
                 if ($resource_identifier != "") {
                   $this_url="//{$_SERVER['HTTP_HOST']}{$THIS_MODULE_PATH}/download.php?resource_identifier={$resource_identifier}&attribute={$attribute}";
                   $file_button_action="onclick=\"window.open('$this_url','_blank');\"";
                 }
               }
               if ($mimetype == "image/jpeg") {
                 $this_image = base64_encode($values_r[0]);
                 print "<img class='img-thumbnail' src='data:image/jpeg;base64,$this_image'>";
                 $description="";
               }
               else {
               ?>
                 <button type="button" <?php print $file_button_action; ?> class="btn btn-secondary" id="<?php print $attribute; ?>-file-info"><?php print $description; ?></button>
               <?php } ?>
               <label class="btn btn-secondary">
                 <?php print $button_text; ?><input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="file" style="display:none" onchange="document.getElementById('<?php print $attribute; ?>-file-info').textContent=this.files[0].name" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>">
               </label>
            <?php
            }
            elseif ($inputtype == "textarea") { ?>
              <textarea <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" rows="4" <?php if ($onkeyup != "") { print "onkeyup=\"$onkeyup\""; } ?>><?php if (isset($values_r[0])) { print htmlspecialchars($values_r[0], ENT_QUOTES, 'UTF-8'); } ?></textarea>
            <?php
            }
            elseif ($inputtype == "tel") { ?>
              <input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="tel" class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" value="<?php if (isset($values_r[0])) { print htmlspecialchars($values_r[0], ENT_QUOTES, 'UTF-8'); } ?>" <?php if ($onkeyup != "") { print "onkeyup=\"$onkeyup\""; } ?>>
            <?php
            }
            elseif ($inputtype == "email") { ?>
              <input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="email" class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" value="<?php if (isset($values_r[0])) { print htmlspecialchars($values_r[0], ENT_QUOTES, 'UTF-8'); } ?>" <?php if ($onkeyup != "") { print "onkeyup=\"$onkeyup\""; } ?>>
            <?php
            }
            elseif ($inputtype == "url") { ?>
              <input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="url" class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" value="<?php if (isset($values_r[0])) { print htmlspecialchars($values_r[0], ENT_QUOTES, 'UTF-8'); } ?>" placeholder="https://" <?php if ($onkeyup != "") { print "onkeyup=\"$onkeyup\""; } ?>>
            <?php
            }
            elseif ($inputtype == "checkbox") {
              // For boolean attributes - treats any non-empty value as TRUE
              $is_checked = (isset($values_r[0]) && $values_r[0] != '' && strcasecmp($values_r[0], 'FALSE') != 0);
              ?>
              <div class="form-check">
                <input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="checkbox" class="form-check-input" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" value="TRUE" <?php if ($is_checked) { print 'checked'; } ?> <?php if ($onkeyup != "") { print "onchange=\"$onkeyup\""; } ?>>
                <label class="form-check-label" for="<?php print $attribute; ?>">
                  Enable
                </label>
              </div>
            <?php
            }
            else { ?>
              <input <?php if (isset($tabindex)) { ?>tabindex="<?php print $tabindex; ?>" <?php } ?>type="text" class="<?php print $field_class; ?>" id="<?php print $attribute; ?>" name="<?php print $attribute; ?>" value="<?php if (isset($values_r[0])) { print $values_r[0]; } ?>" <?php if ($onkeyup != "") { print "onkeyup=\"$onkeyup\""; } ?>>
            <?php
            }
            ?>
       </div>

     </div>

  <?php
}


######################################################

function human_readable_filesize($bytes) {
  for($i = 0; ($bytes / 1024) > 0.9; $i++, $bytes /= 1024) {}
  return round($bytes, [0,0,1,2,2,3,3,4,4][$i]).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
}


######################################################

function render_alert_banner($message,$alert_class="success",$timeout=4000) {

?>
    <script>
      window.setTimeout(function() {
        const alert = document.querySelector('.alert-banner-container .alert');
        if (alert) {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }
      }, <?php print $timeout; ?>);
    </script>
    <div class="container alert-banner-container">
     <div class="alert alert-<?php print $alert_class; ?> alert-dismissible fade show" role="alert">
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <p class="text-center"><?php print $message; ?></p>
     </div>
    </div>
<?php
}

/**
 * ============================================================================
 * TAB RENDERING FUNCTIONS
 * ============================================================================
 * Helper functions to render tabbed interfaces based on configuration arrays
 */

/**
 * Render tab navigation buttons
 * @param array $tabs Tab configuration array
 * @param string $tabs_id ID for the tab navigation ul element
 */
function render_tab_navigation($tabs, $tabs_id = 'userTabs') {
  echo '<ul class="nav nav-tabs" id="' . htmlspecialchars($tabs_id) . '" role="tablist">' . "\n";

  foreach ($tabs as $tab) {
    $active_class = $tab['active'] ? ' active' : '';
    $aria_selected = $tab['active'] ? 'true' : 'false';

    echo '  <li class="nav-item" role="presentation">' . "\n";
    echo '    <button class="nav-link' . $active_class . '" ';
    echo 'id="' . $tab['id'] . '-tab" ';
    echo 'data-bs-toggle="tab" ';
    echo 'data-bs-target="#' . $tab['id'] . '" ';
    echo 'type="button" role="tab" ';
    echo 'aria-controls="' . $tab['id'] . '" ';
    echo 'aria-selected="' . $aria_selected . '">' . "\n";
    echo '      <i class="' . $tab['icon'] . '"></i> ' . htmlspecialchars($tab['label']) . "\n";
    echo '    </button>' . "\n";
    echo '  </li>' . "\n";
  }

  echo '</ul>' . "\n";
}

/**
 * Render tab state persistence JavaScript
 * @param string $storage_key LocalStorage key name
 * @param string $tabs_selector CSS selector for tab buttons
 */
function render_tab_persistence_js($storage_key, $tabs_selector) {
  ?>
<script>
// Tab state persistence
document.addEventListener('DOMContentLoaded', function() {
  // Restore last active tab from localStorage
  const lastTab = localStorage.getItem('<?php echo $storage_key; ?>');
  if (lastTab) {
    const tabButton = document.querySelector(`button[data-bs-target="${lastTab}"]`);
    if (tabButton) {
      const tab = new bootstrap.Tab(tabButton);
      tab.show();
    }
  }

  // Save active tab to localStorage when changed
  const tabButtons = document.querySelectorAll('<?php echo $tabs_selector; ?> button[data-bs-toggle="tab"]');
  tabButtons.forEach(button => {
    button.addEventListener('shown.bs.tab', function(e) {
      localStorage.setItem('<?php echo $storage_key; ?>', e.target.dataset.bsTarget);
    });
  });
});
</script>
  <?php
}


##EoFile
?>
