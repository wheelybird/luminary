<?php

###################################

function open_ldap_connection($ldap_bind=TRUE) {

 global $log_prefix, $LDAP, $SENT_HEADERS, $LDAP_DEBUG, $LDAP_VERBOSE_CONNECTION_LOGS;

 if ($LDAP['ignore_cert_errors'] == TRUE) { putenv('LDAPTLS_REQCERT=never'); }
 $ldap_connection = @ ldap_connect($LDAP['uri']);

 if (!$ldap_connection) {
  print "Problem: Can't connect to the LDAP server at {$LDAP['uri']}";
  die("Can't connect to the LDAP server at {$LDAP['uri']}");
  exit(1);
 }

 ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
 if ($LDAP_VERBOSE_CONNECTION_LOGS == TRUE) { ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7); }

 if (!preg_match("/^ldaps:/", $LDAP['uri'])) {

  $tls_result = @ ldap_start_tls($ldap_connection);

  if ($tls_result != TRUE) {

   if (!preg_match('/^ldap:\/\/127\.0\.0\.([0-9]+)(:[0-9]+)$/', $LDAP['uri'])) { error_log("$log_prefix Failed to start STARTTLS connection to {$LDAP['uri']}: " . ldap_error($ldap_connection),0); }

   if ($LDAP["require_starttls"] == TRUE) {
    print "<div style='position: fixed;bottom: 0;width: 100%;' class='alert alert-danger'>Fatal:  Couldn't create a secure connection to {$LDAP['uri']} and LDAP_REQUIRE_STARTTLS is TRUE.</div>";
    exit(0);
   }
   else {
    if ($SENT_HEADERS == TRUE and !preg_match('/^ldap:\/\/localhost(:[0-9]+)?$/', $LDAP['uri']) and !preg_match('/^ldap:\/\/127\.0\.0\.([0-9]+)(:[0-9]+)$/', $LDAP['uri'])) {
      print "<div style='position: fixed;bottom: 0px;width: 100%;height: 20px;border-bottom:solid 20px yellow;'>WARNING: Insecure LDAP connection to {$LDAP['uri']}</div>";
    }
    ldap_close($ldap_connection);
    $ldap_connection = @ ldap_connect($LDAP['uri']);
    ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
   }
  }
  else {
   if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Start STARTTLS connection to {$LDAP['uri']}",0);
   }
   $LDAP['connection_type'] = "StartTLS";
  }

 }
 else {
  if ($LDAP_DEBUG == TRUE) {
    error_log("$log_prefix Using an LDAPS encrypted connection to {$LDAP['uri']}",0);
   }
   $LDAP['connection_type'] = 'LDAPS';
 }

 if ($ldap_bind == TRUE) {

   if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Attempting to bind to {$LDAP['uri']} as {$LDAP['admin_bind_dn']}",0); }
   $bind_result = @ ldap_bind( $ldap_connection, $LDAP['admin_bind_dn'], $LDAP['admin_bind_pwd']);

   if ($bind_result != TRUE) {

     $this_error = "Failed to bind to {$LDAP['uri']} as {$LDAP['admin_bind_dn']}";
     if ($LDAP_DEBUG == TRUE) { $this_error .= " with password {$LDAP['admin_bind_pwd']}"; }
     $this_error .= ": " . ldap_error($ldap_connection);
     print "Problem: Failed to bind as {$LDAP['admin_bind_dn']}";
     error_log("$log_prefix $this_error",0);

     exit(1);

   }
   elseif ($LDAP_DEBUG == TRUE) {
     error_log("$log_prefix Bound successfully as {$LDAP['admin_bind_dn']}",0);
   }

 }

 return $ldap_connection;

}


###################################

function ldap_auth_username($ldap_connection, $username, $password) {

 # Search for the DN for the given username.  If found, try binding with the DN and user's password.
 # If the binding succeeds, return the DN.

 global $log_prefix, $LDAP, $SITE_LOGIN_LDAP_ATTRIBUTE, $LDAP_DEBUG;

 $ldap_search_query="{$SITE_LOGIN_LDAP_ATTRIBUTE}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER);
 if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Running LDAP search for: $ldap_search_query"); }

 $ldap_search = @ ldap_search( $ldap_connection, $LDAP['user_dn'], $ldap_search_query );

 if (!$ldap_search) {
  error_log("$log_prefix Couldn't search for $ldap_search_query: " . ldap_error($ldap_connection),0);
  return FALSE;
 }

 $result = @ ldap_get_entries($ldap_connection, $ldap_search);
 if (!$result) {
  error_log("$log_prefix Couldn't get LDAP entries for {$username}: " . ldap_error($ldap_connection),0);
  return FALSE;
 }
 if ($LDAP_DEBUG == TRUE) {
   error_log("$log_prefix LDAP search returned " . $result["count"] . " records for $ldap_search_query",0);
   for ($i=1; $i==$result["count"]; $i++) {
     error_log("$log_prefix ". "Entry {$i}: " . $result[$i-1]['dn'], 0);
   }
 }

 if ($result["count"] == 1) {

  $this_dn = $result[0]['dn'];
  if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Attempting authenticate as $username by binding with {$this_dn} ",0); }
  $auth_ldap_connection = open_ldap_connection(FALSE);
  $can_bind =  @ ldap_bind($auth_ldap_connection, $result[0]['dn'], $password);

  if ($can_bind) {
   preg_match("/{$LDAP['account_attribute']}=(.*?),/",$result[0]['dn'],$dn_match);
   $account_id=$dn_match[1];
   if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Able to bind as {$username}: dn is {$result[0]['dn']} and account ID is {$account_id}",0); }
   ldap_close($auth_ldap_connection);
   return $account_id;
  }
  else {
   if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Unable to bind as {$username}: " . ldap_error($auth_ldap_connection),0); }
   ldap_close($auth_ldap_connection);
   return FALSE;
  }

 }
 elseif ($result["count"] > 1) {
   if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix There was more than one entry for {$ldap_search_query} so it wasn't possible to determine which user to log in as."); }
 }

}


###################################

function ldap_setup_auth($ldap_connection, $password) {

 #For the initial setup we need to make sure that whoever's running it has the default admin user
 #credentials as passed in ADMIN_BIND_*
 global $log_prefix, $LDAP, $LDAP_DEBUG;

  if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Initial setup: opening another LDAP connection to test authentication as {$LDAP['admin_bind_dn']}.",0); }
  $auth_ldap_connection = open_ldap_connection();
  $can_bind = @ldap_bind($auth_ldap_connection, $LDAP['admin_bind_dn'], $password);
  ldap_close($auth_ldap_connection);
  if ($can_bind) {
    if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix Initial setup: able to authenticate as {$LDAP['admin_bind_dn']}.",0); }
    return TRUE;
  }
  else {
    $this_error="Initial setup: Unable to authenticate as {$LDAP['admin_bind_dn']}";
    if ($LDAP_DEBUG == TRUE) { $this_error .= " with password $password"; }
    $this_error .= ". The password used to authenticate for /setup should be the same as set by LDAP_ADMIN_BIND_PWD. ";
    $this_error .= ldap_error($ldap_connection);
    error_log("$log_prefix $this_error",0);
    return FALSE;
  }


}


#################################

function generate_salt($length) {

 $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./';

 mt_srand(intval(microtime()) * 1000000);

 $salt = '';
 while (strlen($salt) < $length) {
    $salt .= substr($permitted_chars, (rand() % strlen($permitted_chars)), 1);
  }

 return $salt;

}


##################################

function ldap_hashed_password($password) {

 global $PASSWORD_HASH, $log_prefix;

 $check_algos = array (
                       "SHA512CRYPT" => "CRYPT_SHA512",
                       "SHA256CRYPT" => "CRYPT_SHA256",
#                       "BLOWFISH"    => "CRYPT_BLOWFISH",
#                       "EXT_DES"     => "CRYPT_EXT_DES",
                       "MD5CRYPT"    => "CRYPT_MD5"
                      );

 $remaining_algos = array (
                            "SSHA",
                            "SHA",
                            "SMD5",
                            "MD5",
                            "ARGON2",
                            "CRYPT",
                            "CLEAR"
                          );

 $available_algos = array();

 foreach ($check_algos as $algo_name => $algo_function) {
   if (defined($algo_function) and constant($algo_function) != 0) {
     array_push($available_algos, $algo_name);
   }
   else {
     error_log("$log_prefix password hashing - the system doesn't support {$algo_name}",0);
   }
 }
 $available_algos = array_merge($available_algos, $remaining_algos);

 if (isset($PASSWORD_HASH)) {
   if (!in_array($PASSWORD_HASH, $available_algos)) {
     $hash_algo = $available_algos[0];
     error_log("$log_prefix LDAP password: the chosen hash method ($PASSWORD_HASH) wasn't available",0);
   }
   else {
     $hash_algo = $PASSWORD_HASH;
   }
 }
 else {
   $hash_algo = $available_algos[0];
 }
 error_log("$log_prefix LDAP password: using '{$hash_algo}' as the hashing method",0);

 switch ($hash_algo) {

  case 'SHA512CRYPT':
    $hashed_pwd = '{CRYPT}' . crypt($password, '$6$' . generate_salt(8));
    break;

  case 'SHA256CRYPT':
    $hashed_pwd = '{CRYPT}' . crypt($password, '$5$' . generate_salt(8));
    break;

# Blowfish & EXT_DES didn't work
#  case 'BLOWFISH':
#    $hashed_pwd = '{CRYPT}' . crypt($password, '$2a$12$' . generate_salt(13));
#    break;

#  case 'EXT_DES':
#    $hashed_pwd = '{CRYPT}' . crypt($password, '_' . generate_salt(8));
#    break;

  case 'MD5CRYPT':
    $hashed_pwd = '{CRYPT}' . crypt($password, '$1$' . generate_salt(9));
    break;

  case 'SMD5':
    $salt = generate_salt(8);
    $hashed_pwd = '{SMD5}' . base64_encode(md5($password . $salt, TRUE) . $salt);
    break;

  case 'MD5':
    $hashed_pwd = '{MD5}' . base64_encode(md5($password, TRUE));
    break;

  case 'SHA':
    $hashed_pwd = '{SHA}' . base64_encode(sha1($password, TRUE));
    break;

  case 'SSHA':
    $salt = generate_salt(8);
    $hashed_pwd = '{SSHA}' . base64_encode(sha1($password . $salt, TRUE) . $salt);
    break;

  case 'ARGON2':
    $hashed_pwd = '{ARGON2}' . password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 4, 'threads' => 3]);
    break;

  case 'CRYPT':
    $salt = generate_salt(2);
    $hashed_pwd = '{CRYPT}' . crypt($password, $salt);
    break;

  case 'CLEAR':
    error_log("$log_prefix password hashing - WARNING - Saving password in cleartext. This is extremely bad practice and should never ever be done in a production environment.",0);
    $hashed_pwd = $password;
    break;


 }

 // Log only the algorithm used, never log passwords (even hashed)
 error_log("$log_prefix LDAP password: using '$hash_algo' as the hashing method",0);

 return $hashed_pwd;

}


##################################


function ldap_get_user_list($ldap_connection,$start=0,$entries=NULL,$sort="asc",$sort_key=NULL,$filters=NULL,$fields=NULL) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (!isset($fields)) { $fields = array_unique( array("{$LDAP['account_attribute']}", "givenname", "sn", "mail")); }

 if (!isset($sort_key)) { $sort_key = $LDAP['account_attribute']; }

 $this_filter = "(&({$LDAP['account_attribute']}=*)$filters)";

 $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['user_dn']}", $this_filter, $fields);
 $result = @ ldap_get_entries($ldap_connection, $ldap_search);
 if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP returned {$result['count']} users for {$LDAP['user_dn']} when using this filter: $this_filter",0); }

 $records = array();
 foreach ($result as $record) {

  if (isset($record[$sort_key][0])) {

   $add_these = array();
   foreach($fields as $this_attr) {
    if ($this_attr !== $sort_key and isset($record[$this_attr])) { $add_these[$this_attr] = $record[$this_attr][0]; }
   }

   $records[$record[$sort_key][0]] = $add_these;

  }
 }

 if ($sort == "asc") { ksort($records); } else { krsort($records); }

 return(array_slice($records,$start,$entries));


}

##################################


function fetch_id_stored_in_ldap($ldap_connection,$type="uid") {

  global $log_prefix, $LDAP, $LDAP_DEBUG;

  $filter = "(&(objectclass=device)(cn=last{$type}))";
  $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['base_dn']}", $filter, array('serialNumber'));
  $result = ldap_get_entries($ldap_connection, $ldap_search);

  if (isset($result[0]['serialnumber'][0]) and is_numeric($result[0]['serialnumber'][0])){
    return $result[0]['serialnumber'][0];
  }
  else {
    return FALSE;
  }

}


##################################


function ldap_get_highest_id($ldap_connection,$type="uid") {

 global $log_prefix, $LDAP, $LDAP_DEBUG, $min_uid, $min_gid;

 if ($type == "uid") {
  $this_id = $min_uid;
  $record_base_dn = $LDAP['user_dn'];
  $record_filter = "({$LDAP['account_attribute']}=*)";
  $record_attribute = "uidnumber";
 }
 else {
  $type = "gid";
  $this_id = $min_gid;
  $record_base_dn = $LDAP['group_dn'];
  $record_filter = "(objectClass=posixGroup)";
  $record_attribute = "gidnumber";
 }

 $fetched_id = fetch_id_stored_in_ldap($ldap_connection,$type);

 if ($fetched_id != FALSE) {

  return($fetched_id);

 }
 else {

  error_log("$log_prefix cn=lastGID doesn't exist so the highest $type is determined by searching through all the LDAP records.",0);

  $ldap_search = @ ldap_search($ldap_connection, $record_base_dn, $record_filter, array($record_attribute));
  $result = ldap_get_entries($ldap_connection, $ldap_search);

  foreach ($result as $record) {
   if (isset($record[$record_attribute][0])) {
    if ($record[$record_attribute][0] > $this_id) { $this_id = $record[$record_attribute][0]; }
   }
  }

 }

 return($this_id);

}


##################################


function ldap_get_group_list($ldap_connection,$start=0,$entries=NULL,$sort="asc",$filters=NULL) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 $this_filter = "(&(objectclass=*)$filters)";
 $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $this_filter);

 $result = @ ldap_get_entries($ldap_connection, $ldap_search);
 if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP returned {$result['count']} groups for {$LDAP['group_dn']} when using this filter: $this_filter",0); }

 $records = array();
 foreach ($result as $record) {

  if (isset($record[$LDAP['group_attribute']][0])) {

   array_push($records, $record[$LDAP['group_attribute']][0]);

  }
 }

 if ($sort == "asc") { sort($records); } else { rsort($records); }

 return(array_slice($records,$start,$entries));


}


##################################


function ldap_get_group_entry($ldap_connection,$group_name) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($group_name)) {

  $ldap_search_query = "({$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ")";
  $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query);
  $result = @ ldap_get_entries($ldap_connection, $ldap_search);

  if ($result['count'] > 0) {
    return $result;
  }
  else {
    return FALSE;
  }

 }

 return FALSE;

}

##################################

function ldap_get_group_members($ldap_connection,$group_name,$start=0,$entries=NULL,$sort="asc") {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

 // Ensure group_membership_attribute is set
 if (!isset($LDAP['group_membership_attribute'])) {
  $LDAP['group_membership_attribute'] = 'memberuid';
 }

 $ldap_search_query = "({$LDAP['group_attribute']}=". ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ")";
 $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query, array($LDAP['group_membership_attribute']));

 if ($ldap_search === false) {
  if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP search failed for group {$group_name}: " . ldap_error($ldap_connection), 0); }
  return array();
 }

 $result = @ ldap_get_entries($ldap_connection, $ldap_search);
 if ($result) { $result_count = $result['count']; } else { $result_count = 0; }

 $records = array();

 if ($result_count > 0) {

  // Check if the membership attribute exists in the result
  if (isset($result[0][$LDAP['group_membership_attribute']]) && is_array($result[0][$LDAP['group_membership_attribute']])) {
   foreach ($result[0][$LDAP['group_membership_attribute']] as $key => $value) {

    if ($key !== 'count' and !empty($value)) {
     $this_member = preg_replace("/^.*?=(.*?),.*/", "$1", $value);
     array_push($records, $this_member);
     if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix {$value} is a member",0); }
    }

   }
  }

  $actual_result_count = count($records);
  if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP returned $actual_result_count members of {$group_name} when using this search: $ldap_search_query and this filter: {$LDAP['group_membership_attribute']}",0); }

  if ($actual_result_count > 0) {
   if ($sort == "asc") { sort($records); } else { rsort($records); }
   return(array_slice($records,$start,$entries));
  }
  else {
   return array();
  }

 }
 else {
  return array();
 }

}


##################################

function ldap_is_group_member($ldap_connection,$group_name,$username) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

 $ldap_search_query = "({$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ")";
 $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query);

 if ($ldap_search) {
   $result = ldap_get_entries($ldap_connection, $ldap_search);

   if ($LDAP['group_membership_uses_uid'] == FALSE) {
     $username = "{$LDAP['account_attribute']}=$username,{$LDAP['user_dn']}";
   }

   if (preg_grep ("/^{$username}$/i", $result[0][$LDAP['group_membership_attribute']])) {
     return TRUE;
   }
   else {
     return FALSE;
   }
 }
 else {
  return FALSE;
 }

}


##################################

function ldap_user_group_membership($ldap_connection,$username) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

 if ($LDAP['group_membership_uses_uid'] == FALSE) {
  $username = "{$LDAP['account_attribute']}=$username,{$LDAP['user_dn']}";
 }

 $ldap_search_query = "(&(objectClass=posixGroup)({$LDAP['group_membership_attribute']}={$username}))";
 $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query, array($LDAP['group_attribute']));
 $result = ldap_get_entries($ldap_connection, $ldap_search);

 $groups = array();
 foreach ($result as $record) {
  if (isset($record[$LDAP['group_attribute']][0])) {
   array_push($groups, $record[$LDAP['group_attribute']][0]);
  }
 }
 sort($groups);
 return $groups;

}


##################################

function ldap_new_group($ldap_connection,$group_name,$initial_member="",$extra_attributes=array()) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

 if (isset($group_name)) {

   $new_group = ldap_escape($group_name, "", LDAP_ESCAPE_FILTER);
   $initial_member = ldap_escape($initial_member, "", LDAP_ESCAPE_FILTER);
   $update_gid_store=FALSE;

   $ldap_search_query = "({$LDAP['group_attribute']}=$new_group,{$LDAP['group_dn']})";
   $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query);
   $result = @ ldap_get_entries($ldap_connection, $ldap_search);

   if ($result['count'] == 0) {

     if ($LDAP['group_membership_uses_uid'] == FALSE and $initial_member != "") { $initial_member = "{$LDAP['account_attribute']}=$initial_member,{$LDAP['user_dn']}"; }

     $new_group_array=array( 'objectClass' => $LDAP['group_objectclasses'],
                             'cn' => $new_group
                           );

     // Only add membership attribute if initial_member is not empty (fixes #230 - empty group creation)
     if ($initial_member != "") {
       $new_group_array[$LDAP['group_membership_attribute']] = $initial_member;
     }

     $new_group_array = array_merge($new_group_array,$extra_attributes);

     if (!isset($new_group_array["gidnumber"][0]) or !is_numeric($new_group_array["gidnumber"][0])) {
       $highest_gid = ldap_get_highest_id($ldap_connection,'gid');
       $new_gid = $highest_gid + 1;
       $new_group_array["gidnumber"] = $new_gid;
       $update_gid_store=TRUE;
     }

     $group_dn="cn=$new_group,{$LDAP['group_dn']}";

     $add_group = @ ldap_add($ldap_connection, $group_dn, $new_group_array);

     if (! $add_group ) {
       $this_error="$log_prefix LDAP: unable to add new group ({$group_dn}): " . ldap_error($ldap_connection);
       if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix DEBUG add_group array: ". strip_tags(print_r($new_group_array,true)),0); }
       error_log($this_error,0);
     }
     else {
       error_log("$log_prefix Added new group $group_name",0);

       if ($update_gid_store == TRUE) {
         $this_gid = fetch_id_stored_in_ldap($ldap_connection,"gid");
         if ($this_gid != FALSE) {
           $update_gid = @ ldap_mod_replace($ldap_connection, "cn=lastGID,{$LDAP['base_dn']}", array( 'serialNumber' => $new_gid ));
           if ($update_gid) {
             error_log("$log_prefix Updated cn=lastGID with $new_gid",0);
           }
           else {
             error_log("$log_prefix Unable to update cn=lastGID to $new_gid - this could cause groups to share the same GID.",0);
           }
         }
       }
       return TRUE;
     }

   }
   else {
     error_log("$log_prefix Create group; group $group_name already exists.",0);
   }
 }
 else {
   error_log("$log_prefix Create group; group name wasn't set.",0);
 }

 return FALSE;

}


##################################

function ldap_update_group_attributes($ldap_connection,$group_name,$extra_attributes) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($group_name) and (count($extra_attributes) > 0)) {

  $group_name = ldap_escape($group_name, "", LDAP_ESCAPE_FILTER);
  $group_dn = "{$LDAP['group_attribute']}=$group_name,{$LDAP['group_dn']}";

  $update_group = @ ldap_mod_replace($ldap_connection, $group_dn, $extra_attributes);

  if (!$update_group ) {
    $this_error="$log_prefix LDAP: unable to update group attributes for group ({$group_dn}): " . ldap_error($ldap_connection);
    if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix DEBUG update group attributes array: ". print_r($extra_attributes,true),0); }
    error_log($this_error,0);
    return FALSE;
  }
  else {
    error_log("$log_prefix Updated group attributes for $group_name",0);
    return TRUE;
  }
 }
 else {
  error_log("$log_prefix Update group attributes; group name wasn't set.",0);
  return FALSE;
 }

}

##################################

function ldap_delete_group($ldap_connection,$group_name) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($group_name)) {

  $delete_query = "{$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ",{$LDAP['group_dn']}";
  $delete = @ ldap_delete($ldap_connection, $delete_query);

  if ($delete) {
   error_log("$log_prefix Deleted group $group_name",0);
   return TRUE;
  }
  else {
   error_log("$log_prefix Couldn't delete group $group_name" . ldap_error($ldap_connection) ,0);
   return FALSE;
  }

 }

}


##################################

function ldap_get_gid_of_group($ldap_connection,$group_name) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($group_name)) {

  $ldap_search_query = "({$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ")";
  $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query , array("gidNumber"));
  $result = @ ldap_get_entries($ldap_connection, $ldap_search);

  if (isset($result[0]['gidnumber'][0]) and is_numeric($result[0]['gidnumber'][0])) {
    return $result[0]['gidnumber'][0];
  }

 }

 return FALSE;

}


##################################

function ldap_get_group_name_from_gid($ldap_connection,$gid) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($gid)) {

  $ldap_search_query = "(gidnumber=" . ldap_escape($gid, "", LDAP_ESCAPE_FILTER) . ")";
  $ldap_search = @ ldap_search($ldap_connection, "{$LDAP['group_dn']}", $ldap_search_query , array("cn"));
  $result = @ ldap_get_entries($ldap_connection, $ldap_search);

  if (isset($result[0]['cn'][0])) {
    return $result[0]['cn'][0];
  }

 }

 return FALSE;

}


##################################

function ldap_complete_attribute_array($default_attributes,$additional_attributes) {

  if (isset($additional_attributes)) {

    $user_attribute_r = explode(",", $additional_attributes);
    $to_merge = array();

    foreach ($user_attribute_r as $this_attr) {

      $this_r = array();
      $kv = explode(":", $this_attr);
      $attr_name = strtolower(trim($kv[0]));
      $this_r['inputtype'] = "singleinput";

      if (substr($attr_name, -1) == '+') {
        $this_r['inputtype'] = "multipleinput";
        $attr_name = rtrim($attr_name, '+');
      }

      if (substr($attr_name, -1) == '^') {
        $this_r['inputtype'] = "binary";
        $attr_name = rtrim($attr_name, '^');
      }

      if (preg_match('/^[\p{L}\p{N}\-]+$/u', $attr_name) == 1) {

        if (isset($kv[1]) and $kv[1] != "") {
          $this_r['label'] = trim($kv[1]);
        }
        else {
          $this_r['label'] = $attr_name;
        }

        if (isset($kv[2]) and $kv[2] != "") {
          $this_r['default'] = trim($kv[2]);
        }

        // Support optional 4th parameter for input type (textarea, tel, checkbox, email, url)
        if (isset($kv[3]) and $kv[3] != "") {
          $inputtype = strtolower(trim($kv[3]));
          // Validate input type
          $valid_types = array('textarea', 'tel', 'checkbox', 'email', 'url', 'multipleinput', 'binary');
          if (in_array($inputtype, $valid_types)) {
            $this_r['inputtype'] = $inputtype;
          }
        }

        $to_merge[$attr_name] = $this_r;

      }
    }

    $attribute_r = array_merge($default_attributes, $to_merge);

    return($attribute_r);

  }
  else {
    return($default_attributes);
  }

}


##################################

function ldap_new_account($ldap_connection,$account_r) {

  global $log_prefix, $LDAP, $LDAP_DEBUG, $DEFAULT_USER_SHELL, $DEFAULT_USER_GROUP, $MFA_ENABLED, $MFA_REQUIRED_GROUPS;

  if (    isset($account_r['givenname'][0])
      and isset($account_r['sn'][0])
      and isset($account_r['cn'][0])
      and isset($account_r['uid'][0])
      and isset($account_r[$LDAP['account_attribute']])
      and isset($account_r['password'][0])) {

   $account_identifier = $account_r[$LDAP['account_attribute']][0];
   $user_dn=$LDAP['user_dn'];
   $ldap_search_query = "({$LDAP['account_attribute']}=" . ldap_escape($account_identifier, "", LDAP_ESCAPE_FILTER) . ",$user_dn)";
   $ldap_search = @ ldap_search($ldap_connection, $user_dn, $ldap_search_query);
   $result = @ ldap_get_entries($ldap_connection, $ldap_search);

   if ($result['count'] == 0) {

     $hashed_pass = ldap_hashed_password($account_r['password'][0]);
     unset($account_r['password']);

     $objectclasses = $LDAP['account_objectclasses'];

     $account_attributes = array('objectclass' => $objectclasses,
                                 'userpassword' => $hashed_pass,
                       );

     $account_attributes = array_merge($account_r, $account_attributes);

     if (!isset($account_attributes['uidnumber'][0]) or !is_numeric($account_attributes['uidnumber'][0])) {
       $highest_uid = ldap_get_highest_id($ldap_connection,'uid');
       $account_attributes['uidnumber'][0] = $highest_uid + 1;
     }

     if (!isset($account_attributes['gidnumber'][0]) or !is_numeric($account_attributes['gidnumber'][0])) {
       $default_gid = ldap_get_gid_of_group($ldap_connection,$DEFAULT_USER_GROUP);
       if (!is_numeric($default_gid)) {
         $group_add = ldap_new_group($ldap_connection,$account_identifier,$account_identifier);
         $account_attributes['gidnumber'][0] = ldap_get_gid_of_group($ldap_connection,$account_identifier);
       }
       else {
        $account_attributes['gidnumber'][0] = $default_gid;
        $add_to_group = $DEFAULT_USER_GROUP;
       }
     }
     else {
       $add_to_group = ldap_get_group_name_from_gid($ldap_connection,$account_attributes['gidnumber'][0]);
       if (!$add_to_group) { $add_to_group = $DEFAULT_USER_GROUP; }
     }

     if (empty($account_attributes['loginshell']))    { $account_attributes['loginshell']    = $DEFAULT_USER_SHELL; }
     if (empty($account_attributes['homedirectory'])) { $account_attributes['homedirectory'] = "/home/" . $account_r['uid'][0]; }

     $add_account = @ ldap_add($ldap_connection,
                               "{$LDAP['account_attribute']}=$account_identifier,{$LDAP['user_dn']}",
                               $account_attributes
                              );

     if ($add_account) {
       error_log("$log_prefix Created new account: $account_identifier",0);
       ldap_add_member_to_group($ldap_connection,$add_to_group,$account_identifier);

       $this_uid = fetch_id_stored_in_ldap($ldap_connection,"uid");
       $new_uid = $account_attributes['uidnumber'][0];

       if ($this_uid != FALSE) {
         $update_uid = @ ldap_mod_replace($ldap_connection, "cn=lastUID,{$LDAP['base_dn']}", array( 'serialNumber' => $new_uid ));
         if ($update_uid) {
           error_log("$log_prefix Create account; Updated cn=lastUID with $new_uid",0);
         }
         else {
           error_log("$log_prefix Unable to update cn=lastUID to $new_uid - this could cause user accounts to share the same UID.",0);
         }
       }

       // Initialise MFA if enabled and user is in required group
       if ($MFA_ENABLED && !empty($MFA_REQUIRED_GROUPS)) {
         include_once "totp_functions.inc.php";
         $user_dn = "{$LDAP['account_attribute']}=$account_identifier,{$LDAP['user_dn']}";

         if (totp_user_requires_mfa($ldap_connection, $account_identifier, $MFA_REQUIRED_GROUPS)) {
           // Add totpUser object class and set status to pending
           $mfa_modifications = array(
             'objectClass' => array_merge($objectclasses, array('totpUser')),
             'totpStatus' => 'pending',
             'totpEnrolledDate' => gmdate('YmdHis') . 'Z',
           );

           $add_mfa = @ ldap_mod_replace($ldap_connection, $user_dn, $mfa_modifications);
           if ($add_mfa) {
             error_log("$log_prefix Create account; Initialised MFA (pending) for $account_identifier",0);
           } else {
             error_log("$log_prefix Create account; Failed to initialise MFA for $account_identifier: " . ldap_error($ldap_connection),0);
           }
         }
       }

       return TRUE;
     }
     else {
       ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
       error_log("$log_prefix Create account; couldn't create the account for {$account_identifier}: " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
     }

   }

   else {
     error_log("$log_prefix Create account; Account for {$account_identifier} already exists",0);
   }

  }
  else {
    error_log("$log_prefix Create account; missing parameters",0);
  }

  return FALSE;

}


##################################

function ldap_delete_account($ldap_connection,$username) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 if (isset($username)) {

  // First, remove user from all groups (fixes #215, #169)
  $user_groups = ldap_user_group_membership($ldap_connection, $username);

  foreach ($user_groups as $group) {
    $removed = ldap_delete_member_from_group($ldap_connection, $group, $username);
    if (!$removed) {
      error_log("$log_prefix Warning: Failed to remove $username from group $group during account deletion",0);
    }
  }

  // Now delete the user account
  $delete_query = "{$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ",{$LDAP['user_dn']}";
  $delete = @ ldap_delete($ldap_connection, $delete_query);

  if ($delete) {
   error_log("$log_prefix Deleted account for $username",0);
   return TRUE;
  }
  else {
   error_log("$log_prefix Couldn't delete account for {$username}: " . ldap_error($ldap_connection),0);
   return FALSE;
  }

 }

}


##################################

function ldap_add_member_to_group($ldap_connection,$group_name,$username) {

  global $log_prefix, $LDAP, $LDAP_DEBUG;

  $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

  $group_dn = "{$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ",{$LDAP['group_dn']}";

  if ($LDAP['group_membership_uses_uid'] == FALSE) {
   $username = "{$LDAP['account_attribute']}=$username,{$LDAP['user_dn']}";
  }

  $group_update = array($LDAP['group_membership_attribute'] => $username);
  $update = @ ldap_mod_add($ldap_connection,$group_dn,$group_update);

  if ($update) {
   error_log("$log_prefix Added $username to group '$group_name'",0);
   return TRUE;
  }
  else {
   ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
   error_log("$log_prefix Couldn't add $username to group '{$group_name}': " . ldap_error($ldap_connection) . " -- " . $detailed_err,0);
   return FALSE;
  }

}


##################################

function ldap_delete_member_from_group($ldap_connection,$group_name,$username) {

  global $log_prefix, $LDAP, $LDAP_DEBUG, $USER_ID;

  if ($group_name == $LDAP['admins_group'] and $username == $USER_ID) {
    error_log("$log_prefix Won't remove {$username} from {$group_name} because you're logged in as {$username} and {$group_name} is the admin group.",0);
    return FALSE;
  }
  else {
    $rfc2307bis_available = ldap_detect_rfc2307bis($ldap_connection);

    $group_dn = "{$LDAP['group_attribute']}=" . ldap_escape($group_name, "", LDAP_ESCAPE_FILTER) . ",{$LDAP['group_dn']}";

    if ($LDAP['group_membership_uses_uid'] == FALSE and $username != "") {
      $username = "{$LDAP['account_attribute']}=$username,{$LDAP['user_dn']}";
    }

    $group_update = array($LDAP['group_membership_attribute'] => $username);
    $update = @ ldap_mod_del($ldap_connection,$group_dn,$group_update);

    if ($update) {
     error_log("$log_prefix Removed '$username' from $group_name",0);
     return TRUE;
    }
    else {
     error_log("$log_prefix Couldn't remove '$username' from {$group_name}: " . ldap_error($ldap_connection),0);
     return FALSE;
    }
  }
}


##################################

function ldap_change_password($ldap_connection,$username,$new_password) {

 global $log_prefix, $LDAP, $LDAP_DEBUG;

 #Find DN of user

 $ldap_search_query = "{$LDAP['account_attribute']}=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER);
 $ldap_search = @ ldap_search( $ldap_connection, $LDAP['user_dn'], $ldap_search_query);
 if ($ldap_search) {
  $result = @ ldap_get_entries($ldap_connection, $ldap_search);
  if ($result["count"] == 1) {
  $this_dn=$result[0]['dn'];
  }
  else {
   error_log("$log_prefix Couldn't find the DN for user $username");
   return FALSE;
  }
 }
 else {
  error_log("$log_prefix Couldn't perform an LDAP search for {$LDAP['account_attribute']}={$username}: " . ldap_error($ldap_connection),0);
  return FALSE;
 }

 $entries["userPassword"] = ldap_hashed_password($new_password);
 $update = @ ldap_mod_replace($ldap_connection, $this_dn, $entries);

 if ($update) {
  error_log("$log_prefix Updated the password for $username",0);
  return TRUE;
 }
 else {
  error_log("$log_prefix Couldn't update the password for {$username}: " . ldap_error($ldap_connection),0);
  return TRUE;
 }

}


##################################

function ldap_detect_rfc2307bis($ldap_connection) {

  global $log_prefix, $LDAP, $LDAP_DEBUG;

  if (isset($LDAP['rfc2307bis_available'])) {
    return $LDAP['rfc2307bis_available'];
  }
  else {

    $LDAP['rfc2307bis_available'] = FALSE;

    if ($LDAP['forced_rfc2307bis'] == TRUE) {
      if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - skipping autodetection because FORCE_RFC2307BIS is TRUE",0); }
      $LDAP['rfc2307bis_available'] = TRUE;
    }
    else {

      $schema_base_query = @ ldap_read($ldap_connection,"","subschemaSubentry=*",array('subschemaSubentry'));

      if (!$schema_base_query) {
        error_log("$log_prefix LDAP RFC2307BIS detection - unable to query LDAP for objectClasses under {$schema_base_dn}:" . ldap_error($ldap_connection),0);
        error_log("$log_prefix LDAP RFC2307BIS detection - we'll assume that the RFC2307BIS schema isn't available.  Set FORCE_RFC2307BIS to TRUE if you DO use RFC2307BIS.",0);
      }
      else {
        $schema_base_results = @ ldap_get_entries($ldap_connection, $schema_base_query);

        if ($schema_base_results) {

          $schema_base_dn = $schema_base_results[0]['subschemasubentry'][0];
          if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - found that the 'subschemaSubentry' base DN is '$schema_base_dn'",0); }

          $objclass_query = @ ldap_read($ldap_connection,$schema_base_dn,"(objectClasses=*)",array('objectClasses'));
          if (!$objclass_query) {
            error_log("$log_prefix LDAP RFC2307BIS detection - unable to query LDAP for objectClasses under {$schema_base_dn}:" . ldap_error($ldap_connection),0);
          }
          else {
            $objclass_results = @ ldap_get_entries($ldap_connection, $objclass_query);
            $this_count = $objclass_results[0]['objectclasses']['count'];
            if ($this_count > 0) {
              if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - found $this_count objectClasses under $schema_base_dn" ,0); }
              $posixgroup_search = preg_grep("/NAME 'posixGroup'.*AUXILIARY/",$objclass_results[0]['objectclasses']);
              if (count($posixgroup_search) > 0) {
                if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - found AUXILIARY in posixGroup definition which suggests we're using the RFC2307BIS schema" ,0); }
                $LDAP['rfc2307bis_available'] = TRUE;
              }
              else {
                if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - couldn't find AUXILIARY in the posixGroup definition which suggests we're not using the RFC2307BIS schema.  Set FORCE_RFC2307BIS to TRUE if you DO use RFC2307BIS. " ,0); }
              }
            }
            else {
              if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - no objectClasses were returned when searching under $schema_base_dn" ,0); }
            }
          }
        }
        else {
         if ($LDAP_DEBUG == TRUE) { error_log("$log_prefix LDAP RFC2307BIS detection - unable to detect the subschemaSubentry base DN" ,0); }
        }
      }
    }

    if ($LDAP['rfc2307bis_available'] == TRUE) {
      if (!isset($LDAP['group_membership_attribute'])) { $LDAP['group_membership_attribute'] = 'uniquemember'; }
      if (!isset($LDAP['group_membership_uses_uid'])) { $LDAP['group_membership_uses_uid'] = FALSE; }
      if (!in_array('groupOfUniqueNames',$LDAP['group_objectclasses'])) { array_push($LDAP['group_objectclasses'], 'groupOfUniqueNames'); }
      return TRUE;
    }
    else {
      if (!isset($LDAP['group_membership_attribute'])) { $LDAP['group_membership_attribute'] = 'memberuid'; }
      if (!isset($LDAP['group_membership_uses_uid'])) { $LDAP['group_membership_uses_uid'] = TRUE; }
      return FALSE;
    }

  }

}

###################################
# Unicode Support Helper Functions
###################################

/**
 * Validate text fields (names, descriptions) - allows Unicode characters
 *
 * @param string $text The text to validate
 * @param int $min Minimum length (default: 1)
 * @param int $max Maximum length (default: 100)
 * @return bool True if valid, false otherwise
 */
function validate_text($text, $min = 1, $max = 100) {
    mb_internal_encoding('UTF-8');

    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        return false;
    }

    // Allow Unicode letters, combining marks, spaces, hyphens, apostrophes
    return preg_match('/^[\p{L}\p{M}\s\'-]+$/u', $text) === 1;
}

/**
 * Validate usernames - allows Unicode letters/numbers plus common symbols
 *
 * @param string $username The username to validate
 * @return bool True if valid, false otherwise
 */
function validate_username($username) {
    mb_internal_encoding('UTF-8');

    $length = mb_strlen($username);
    if ($length < 2 || $length > 64) {
        return false;
    }

    // Unicode letters, numbers, underscore, dot, hyphen
    if (!preg_match('/^[\p{L}\p{N}_.-]+$/u', $username)) {
        return false;
    }

    // Don't allow leading/trailing dots or hyphens
    if (preg_match('/^[.-]|[.-]$/u', $username)) {
        return false;
    }

    return true;
}

/**
 * Sanitize value for LDAP storage
 *
 * @param string $value The value to sanitize
 * @return string The sanitized value safe for LDAP
 */
function sanitize_for_ldap($value) {
    $value = trim($value);
    return ldap_escape($value, '', LDAP_ESCAPE_DN);
}

/**
 * Sanitize value for HTML display
 *
 * @param string $value The value to sanitize
 * @return string The sanitized value safe for HTML
 */
function sanitize_for_html($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Decode HTML entities from LDAP values for backward compatibility
 *
 * Converts old data that was incorrectly stored with HTML entities
 * (e.g., "M&uuml;ller" -> "Müller") back to proper UTF-8.
 *
 * This function handles legacy data created before Unicode support
 * was properly implemented. As users edit entries, data will naturally
 * migrate to pure UTF-8 storage.
 *
 * @param string|array $value Value from LDAP (can be string or array)
 * @return string|array Decoded value in the same format as input
 *
 * @deprecated TODO: Remove in v2.0 after data migration period
 */
function decode_ldap_value($value) {
    // Handle arrays (multi-valued attributes)
    if (is_array($value)) {
        $decoded = array();
        foreach ($value as $key => $val) {
            // Skip 'count' key that LDAP arrays include
            if ($key !== 'count') {
                $decoded[$key] = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $decoded[$key] = $val;
            }
        }
        return $decoded;
    }

    // Handle single values
    if (is_string($value)) {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Return unchanged for other types (null, bool, etc.)
    return $value;
}

?>
