<?php
/**
 * PHPMailer factory shared by all senders (contact form, whitepaper,
 * marketing emails). Configuration comes from lib/config.php.
 */

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Returns a configured-but-unaddressed PHPMailer instance. */
function make_mailer() {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = MAIL_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = MAIL_USER;
  $mail->Password   = MAIL_PASS;
  $mail->SMTPSecure = MAIL_PORT === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = MAIL_PORT;
  $mail->CharSet    = 'UTF-8';
  // Fail fast if the SMTP host is unreachable. Without this, PHPMailer's default
  // 300s timeout outlives PHP's max_execution_time, so a bad host produces an
  // uncatchable fatal 500 instead of a catchable exception — which would break
  // the contact/careers forms even though email delivery is meant to be non-fatal.
  $mail->Timeout    = 8;
  $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
  return $mail;
}
