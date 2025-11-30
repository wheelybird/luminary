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


function parse_mail_text($template,$password,$login,$first_name,$last_name) {

  global $ORGANISATION_NAME, $SITE_PROTOCOL, $SERVER_HOSTNAME, $SERVER_PATH;

  $template = str_replace("{password}", $password, $template);
  $template = str_replace("{login}", $login, $template);
  $template = str_replace("{first_name}", $first_name, $template);
  $template = str_replace("{last_name}", $last_name, $template);

  $template = str_replace("{organisation}", $ORGANISATION_NAME, $template);
  $template = str_replace("{site_url}", "{$SITE_PROTOCOL}{$SERVER_HOSTNAME}{$SERVER_PATH}", $template);
  $template = str_replace("{change_password_url}", "{$SITE_PROTOCOL}{$SERVER_HOSTNAME}{$SERVER_PATH}change_password", $template);

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

  if (isset($SMTP['user'])) {
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
