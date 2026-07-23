<?php
/**
 * Public contact form handler.
 *
 * Flow: honeypot → validate → rate-limit + store encrypted lead → email via
 * Resend. The stored lead is the durable record, so if Resend is unavailable
 * the submission is still captured (soft success) and the failure is logged.
 * Mirrors blog-lead-handler.php; source='contact', service holds the subject.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/crypto.php';
require __DIR__ . '/lib/geo.php';
require __DIR__ . '/lib/resend.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit(json_encode(['error' => 'Method not allowed']));
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

// ── Honeypot: a real browser leaves this hidden field empty. Bots fill it.
// Return a normal success so the bot can't tell it was rejected.
if (trim($body['website'] ?? '') !== '') {
  http_response_code(200);
  exit(json_encode(['success' => true]));
}

$name    = trim($body['name'] ?? '');
$email   = trim($body['email'] ?? '');
$phone   = trim($body['phone'] ?? '');
$company = trim($body['company'] ?? '');
$subject = trim($body['subject'] ?? '');
$message = trim($body['message'] ?? '');

// ── Validation (mandatory: name, email, message).
if ($name === '') {
  http_response_code(400);
  exit(json_encode(['error' => 'Please enter your name.']));
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  exit(json_encode(['error' => 'Please enter a valid email address.']));
}
if ($message === '') {
  http_response_code(400);
  exit(json_encode(['error' => 'Please enter a message.']));
}
// Phone is optional. Per-country validation happens client-side (libphonenumber);
// here we only sanity-check the shape of the E.164 value the client sends.
if ($phone !== '' && !preg_match('/^\+?[0-9 ().-]{6,20}$/', $phone)) {
  http_response_code(400);
  exit(json_encode(['error' => 'Please enter a valid phone number.']));
}

// Guard against oversized payloads.
if (strlen($message) > 5000 || strlen($name) > 200 || strlen($company) > 200 || strlen($subject) > 200) {
  http_response_code(400);
  exit(json_encode(['error' => 'One of the fields is too long.']));
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// ── Rate limit + store. DB errors are non-fatal: we still try to email so a
// transient DB issue doesn't silently drop the enquiry.
$stored = false;
try {
  // Max 5 submissions per IP per 10 minutes.
  $rl = db()->prepare(
    'SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)'
  );
  $rl->execute([$ip]);
  if ((int) $rl->fetchColumn() >= 5) {
    http_response_code(429);
    exit(json_encode(['error' => 'Too many submissions. Please try again in a few minutes.']));
  }

  db()->prepare(
    'INSERT INTO leads
       (id, source, name_enc, email_bi, email_enc, company_enc, phone_enc, service, message_enc, ip, country, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
  )->execute([
    db_new_id(), 'contact',
    encrypt_value($name), blind_index($email), encrypt_value($email),
    $company !== '' ? encrypt_value($company) : null,
    $phone   !== '' ? encrypt_value($phone)   : null,
    $subject,
    encrypt_value($message),
    $ip, geo_country($ip),
  ]);
  $stored = true;
} catch (Throwable $e) {
  error_log('Contact lead store error: ' . $e->getMessage());
}

// ── Email via Resend. Reply-To is the visitor so a reply goes straight to them.
$esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$rows = [
  'Name'    => $name,
  'Email'   => $email,
  'Phone'   => $phone !== '' ? $phone : '—',
  'Company' => $company !== '' ? $company : '—',
  'Subject' => $subject !== '' ? $subject : '—',
];
$rowsHtml = '';
foreach ($rows as $label => $val) {
  $rowsHtml .= '<tr>'
    . '<td style="padding:6px 14px 6px 0;color:#666;vertical-align:top;white-space:nowrap;">' . $esc($label) . '</td>'
    . '<td style="padding:6px 0;color:#111;">' . $esc($val) . '</td>'
    . '</tr>';
}
$mailSubject = 'New contact form message' . ($subject !== '' ? ': ' . $subject : '');
$html =
  '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;">'
  . '<h2 style="margin:0 0 16px;font-size:18px;">New message from the pandit.guru contact form</h2>'
  . '<table style="border-collapse:collapse;margin-bottom:16px;">' . $rowsHtml . '</table>'
  . '<div style="padding:14px 16px;background:#f6f6f6;border-radius:8px;white-space:pre-wrap;color:#111;">'
  . nl2br($esc($message)) . '</div>'
  . '</div>';

[$sent, $sendErr] = resend_send(CONTACT_TO, $mailSubject, $html, $email);
if (!$sent) {
  error_log('Contact email send failed: ' . $sendErr);
}

// Both the durable store and the email failed → real error the visitor should retry.
if (!$stored && !$sent) {
  http_response_code(500);
  exit(json_encode(['error' => 'Sorry, something went wrong. Please email hello@pandit.guru directly.']));
}

http_response_code(200);
exit(json_encode(['success' => true]));
