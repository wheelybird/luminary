<?php

require_once "/opt/PHPMailer/src/PHPMailer.php";
require_once "/opt/PHPMailer/src/SMTP.php";
require_once "/opt/PHPMailer/src/Exception.php";

#Default email text

$new_account_mail_subject = (getenv('NEW_ACCOUNT_EMAIL_SUBJECT') ? getenv('NEW_ACCOUNT_EMAIL_SUBJECT') : "Your {organisation} account has been created.");
$new_account_mail_body = getenv('NEW_ACCOUNT_EMAIL_BODY') ?: <<<EoNA
You've been set up with an account for {organisation}.  Your credentials are:
<p>
Login: {login}<br>
Password: {password}
<p>
You should log into <a href="{change_password_url}">{change_password_url}</a> and change the password as soon as possible.
EoNA;

$reset_password_mail_subject = (getenv('RESET_PASSWORD_EMAIL_SUBJECT') ? getenv('RESET_PASSWORD_EMAIL_SUBJECT') : "Your {organisation} password has been reset.");
$reset_password_mail_body = getenv('RESET_PASSWORD_EMAIL_BODY') ?: <<<EoRP
Your password for {organisation} has been reset.  Your new password is {password}
<p>
You should log into <a href="{change_password_url}">{change_password_url}</a> and change this password as soon as possible.
EoRP;

$password_changed_mail_subject = (getenv('PASSWORD_CHANGED_EMAIL_SUBJECT') ? getenv('PASSWORD_CHANGED_EMAIL_SUBJECT') : "Your {organisation} password has been changed.");
$password_changed_mail_body = getenv('PASSWORD_CHANGED_EMAIL_BODY') ?: <<<EoPC
Your password for {organisation} was changed on {date} at {time} from IP address {ip}.
<p>
If you did not make this change, please contact the administrator immediately{admin_contact}.
<p>
For security, you can change your password at any time: <a href="{change_password_url}">{change_password_url}</a>
EoPC;

$admin_reset_mail_subject = (getenv('ADMIN_RESET_EMAIL_SUBJECT') ? getenv('ADMIN_RESET_EMAIL_SUBJECT') : "Your {organisation} password has been reset by an administrator.");
$admin_reset_mail_body = getenv('ADMIN_RESET_EMAIL_BODY') ?: <<<EoAR
Your password for {organisation} has been reset by an administrator.
<p>
Please contact the administrator{admin_contact} to receive your new password.
EoAR;

$password_reset_request_mail_subject = (getenv('PASSWORD_RESET_REQUEST_EMAIL_SUBJECT') ? getenv('PASSWORD_RESET_REQUEST_EMAIL_SUBJECT') : "Password reset request for {organisation}");
$password_reset_request_mail_body = getenv('PASSWORD_RESET_REQUEST_EMAIL_BODY') ?: <<<EoPRR
A password reset was requested for your {organisation} account.
<p>
Click the link below to reset your password:<br>
<a href="{reset_url}">{reset_url}</a>
<p>
This link will expire in {expiry_minutes} minutes.
<p>
If you did not request this reset, please ignore this email{admin_contact}.
<p>
For security, password reset links can only be used once.
EoPRR;

$password_reset_success_mail_subject = (getenv('PASSWORD_RESET_SUCCESS_EMAIL_SUBJECT') ? getenv('PASSWORD_RESET_SUCCESS_EMAIL_SUBJECT') : "Your {organisation} password has been reset");
$password_reset_success_mail_body = getenv('PASSWORD_RESET_SUCCESS_EMAIL_BODY') ?: <<<EoPRS
Your password for {organisation} was successfully reset on {date} at {time} from IP address {ip}.
<p>
If you did not perform this reset, please contact the administrator immediately{admin_contact}.
<p>
You can log in now with your new password: <a href="{site_url}">{site_url}</a>
EoPRS;


function parse_mail_text($template,$password,$login,$first_name,$last_name,$timestamp=null,$ip=null,$admin_email=null,$reset_url=null,$expiry_minutes=null) {

  global $ORGANISATION_NAME, $SITE_PROTOCOL, $SERVER_HOSTNAME, $SERVER_PATH;

  $template = str_replace("{password}", $password, $template);
  $template = str_replace("{login}", $login, $template);
  $template = str_replace("{first_name}", $first_name, $template);
  $template = str_replace("{last_name}", $last_name, $template);

  $template = str_replace("{organisation}", $ORGANISATION_NAME, $template);
  $template = str_replace("{site_url}", "{$SITE_PROTOCOL}{$SERVER_HOSTNAME}{$SERVER_PATH}", $template);
  $template = str_replace("{change_password_url}", "{$SITE_PROTOCOL}{$SERVER_HOSTNAME}{$SERVER_PATH}change_password", $template);

  // Handle timestamp and IP for password change notifications
  if ($timestamp !== null) {
    $date = date('d F Y', $timestamp);
    $time = date('H:i:s T', $timestamp);
    $template = str_replace("{date}", $date, $template);
    $template = str_replace("{time}", $time, $template);
  }

  if ($ip !== null) {
    $template = str_replace("{ip}", $ip, $template);
  }

  // Handle admin contact info
  if ($admin_email !== null && !empty($admin_email)) {
    $admin_contact = " at <a href=\"mailto:$admin_email\">$admin_email</a>";
  } else {
    $admin_contact = "";
  }
  $template = str_replace("{admin_contact}", $admin_contact, $template);

  // Handle password reset placeholders
  if ($reset_url !== null) {
    $template = str_replace("{reset_url}", $reset_url, $template);
  }

  if ($expiry_minutes !== null) {
    $template = str_replace("{expiry_minutes}", $expiry_minutes, $template);
  }

  return $template;

}

function send_email($recipient_email,$recipient_name,$subject,$body) {

  global $EMAIL, $SMTP, $log_prefix;

  $mail = new PHPMailer\PHPMailer\PHPMailer();
  $mail->CharSet = 'UTF-8';
  $mail->isSMTP();

  $mail->SMTPDebug = $SMTP['debug_level'];
  $mail->Debugoutput = function($message, $level) { error_log("$log_prefix SMTP (level $level): $message"); };

  $mail->Host = $SMTP['host'];
  $mail->Port = $SMTP['port'];

  if (isset($SMTP['helo'])) {
    $mail->Helo = $SMTP['helo'];
  }

  if (!empty($SMTP['user'])) {
    $mail->SMTPAuth = true;
    $mail->Username = $SMTP['user'];
    $mail->Password = $SMTP['pass'];
  }

  if ($SMTP['tls'] == TRUE) { $mail->SMTPSecure = 'tls'; }
  if ($SMTP['ssl'] == TRUE) { $mail->SMTPSecure = 'ssl'; }

  $mail->SMTPAutoTLS = false;
  $mail->setFrom($EMAIL['from_address'], $EMAIL['from_name']);
  if (!empty($EMAIL['reply_to_address'])) {
    $mail->addReplyTo($EMAIL['reply_to_address'], $EMAIL['from_name']);
  }
  $mail->addAddress($recipient_email, $recipient_name);
  $mail->Subject = $subject;
  $mail->Body = $body;
  $mail->IsHTML(true);

  if (!$mail->Send())  {
    error_log("$log_prefix SMTP: Unable to send email: " . $mail->ErrorInfo);
    return FALSE;
  }
  else {
    error_log("$log_prefix SMTP: sent an email to $recipient_email ($recipient_name)");
    return TRUE;
  }

}

?>
