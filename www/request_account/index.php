<?php

set_include_path( ".:" . __DIR__ . "/../includes/");
session_start();

include_once "web_functions.inc.php";

render_header("$ORGANISATION_NAME - request an account");

if ($ACCOUNT_REQUESTS_ENABLED == FALSE) {

?><div class='alert alert-warning'><p class='text-center'>Account requesting is disabled.</p></div><?php

render_footer();
exit(0);

}

if($_POST) {

  $error_messages = array();

  if(! isset($_POST['validate']) or strcasecmp($_POST['validate'], $_SESSION['proof_of_humanity']) != 0) {
    array_push($error_messages, "The validation text didn't match the image.");
  }

  if (! isset($_POST['firstname']) or $_POST['firstname'] == "") {
    array_push($error_messages, "You didn't enter your first name.");
  }
  else {
    $firstname=trim($_POST['firstname']);
  }

  if (! isset($_POST['lastname']) or $_POST['lastname'] == "") {
    array_push($error_messages, "You didn't enter your first name.");
  }
  else {
    $lastname=trim($_POST['lastname']);
  }

  if (isset($_POST['email']) and $_POST['email'] != "") {
    $email=filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
  }

  if (isset($_POST['notes']) and $_POST['notes'] != "") {
    $notes=trim($_POST['notes']);
  }


  if (count($error_messages) > 0) { ?>
    <div class="alert alert-danger" role="alert">
    The request couldn't be sent because:
    <p>
    <ul>
      <?php
       foreach($error_messages as $message) {
         print "<li>$message</li>\n";
       }
      ?>
    </ul>
    </div>
  <?php
  }
  else {

    $mail_subject = "$firstname $lastname has requested an account for $ORGANISATION_NAME.";

$link_url="{$SITE_PROTOCOL}{$SERVER_HOSTNAME}{$SERVER_PATH}account_manager/new_user.php?account_request&first_name=$firstname&last_name=$lastname&email=$email";

if (!isset($email)) { $email = "n/a"; }
if (!isset($notes)) { $notes = "n/a"; }

    $mail_body = <<<EoT
A request for an $ORGANISATION_NAME account has been sent:
<p>
First name: <b>$firstname</b><br>
Last name: <b>$lastname</b><br>
Email: <b>$email</b><br>
Notes: <pre>$notes</pre><br>
<p>
<a href="$link_url">Create this account.</a>
EoT;

     include_once "mail_functions.inc.php";
     $sent_email = send_email($ACCOUNT_REQUESTS_EMAIL,"$ORGANISATION_NAME account requests",$mail_subject,$mail_body);
     if ($sent_email) { ?>
       <div class="container">
         <div class="col-sm-6 col-sm-offset-3">
           <div class="panel panel-success">
             <div class="panel-heading">Thank you</div>
             <div class="panel-body">
               The request was sent and the administrator will process it as soon as possible.
             </div>
           </div>
         </div>
       </div>
     <?php }
     else { ?>
       <div class="container">
         <div class="col-sm-6 col-sm-offset-3">
           <div class="panel panel-danger">
             <div class="panel-heading">Error</div>
             <div class="panel-body">
               Unfortunately the account request wasn't sent because of a technical issue.
             </div>
           </div>
         </div>
       </div>
    <?php
    }
   render_footer();
   exit(0);

  }
}
?>
<div class="container">
 <div class="col-sm-8 col-sm-offset-2">

  <div class="panel panel-default">
    <div class="panel-body">
    Use this form to send a request for an account to an administrator at <?php print $ORGANISATION_NAME; ?>.
    If the administrator approves your request they'll get in touch with you to give you your new credentials.
    </div>
  </div>

  <div class="panel panel-default"> 
   <div class="panel-heading text-center">Request an account for <?php print $ORGANISATION_NAME; ?></div>
   <div class="panel-body text-center">

   <form class="form-horizontal" action='' method='post'>

    <div class="form-group">
     <label for="firstname" class="col-sm-4 control-label">First name</label>
     <div class="col-sm-6">
      <input type="text" class="form-control" id="firstname" name="firstname" placeholder="Required" <?php if (isset($firstname)) { print "value='$firstname'"; } ?>>
     </div>
    </div>

    <div class="form-group">
     <label for="lastname" class="col-sm-4 control-label">Last name</label>
     <div class="col-sm-6">
      <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Required" <?php if (isset($lastname)) { print "value='$lastname'"; } ?>>
     </div>
    </div>

    <div class="form-group">
     <label for="email" class="col-sm-4 control-label">Email</label>
     <div class="col-sm-6">
      <input type="text" class="form-control" id="email" name="email" <?php if (isset($email)) { print "value='$email'"; } ?>>
     </div>
    </div>

    <div class="form-group">
     <label for="Notes" class="col-sm-4 control-label">Notes</label>
     <div class="col-sm-6">
      <textarea class="form-control" id="notes" name="notes" placeholder="Enter any extra information you think the administrator might need to know."><?php if (isset($notes)) { print $notes; } ?></textarea>
     </div>
    </div>

    <div class="form-group">
     <label for="validate" class="col-sm-4 control-label">Validation</label>
     <div class="col-sm-6">
      <span class="center-block">
        <img src="human.php" class="human-check" alt="Non-human detection">
        <button type="button" class="btn btn-default btn-sm" onclick="document.querySelector('.human-check').src = 'human.php?' + Date.now()">
         <span class="glyphicon glyphicon-refresh"></span> Refresh
        </button>
      </span>
      <input type="text" class="form-control center-block" id="validate" name="validate" placeholder="Enter the characters from the image">
     </div>
    </div>

    <div class="form-group">
     <button type="submit" class="btn btn-default">Send request</button>
    </div>
   
   </form>
  </div>
 </div>
</div>
<?php
render_footer();
?>
