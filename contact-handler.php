<?php
/**
 * Public contact form handler.
 *
 * Flow: honeypot → validate fields → validate attachments → rate-limit + store
 * encrypted lead → email via Resend (with attachments). The stored lead is the
 * durable record, so if Resend is unavailable the submission is still captured
 * (soft success) and the failure is logged. Attachments live only on the email.
 *
 * Request is multipart/form-data (fields in $_POST, files in $_FILES).
 * source='contact', service holds the subject.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/crypto.php';
require __DIR__ . '/lib/geo.php';
require __DIR__ . '/lib/resend.php';

header('Content-Type: application/json');

function fail($code, $msg) {
  http_response_code($code);
  exit(json_encode(['error' => $msg]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  fail(405, 'Method not allowed');
}

// ── Honeypot: a real browser leaves this hidden field empty. Bots fill it.
// Return a normal success so the bot can't tell it was rejected.
if (trim($_POST['website'] ?? '') !== '') {
  http_response_code(200);
  exit(json_encode(['success' => true]));
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$company = trim($_POST['company'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// ── Field validation (mandatory: name, email, message).
if ($name === '')    fail(400, 'Please enter your name.');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(400, 'Please enter a valid email address.');
if ($message === '') fail(400, 'Please enter a message.');
// Phone is optional; per-country validation is client-side (libphonenumber).
if ($phone !== '' && !preg_match('/^\+?[0-9 ().-]{6,20}$/', $phone)) fail(400, 'Please enter a valid phone number.');
if (strlen($message) > 5000 || strlen($name) > 200 || strlen($company) > 200 || strlen($subject) > 200) {
  fail(400, 'One of the fields is too long.');
}

// ── Attachment validation: up to 3 files, 5 MB total, docs + images only.
const MAX_FILES = 3;
const MAX_TOTAL_BYTES = 5 * 1024 * 1024;
// Allowed extension => acceptable finfo MIME types. OOXML files (docx/xlsx/pptx)
// are zip containers, so finfo may report application/zip — accepted only when
// the extension also matches, which keeps bare .zip uploads out.
$ALLOWED = [
  'pdf'  => ['application/pdf'],
  'doc'  => ['application/msword', 'application/octet-stream'],
  'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
  'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
  'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
  'ppt'  => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
  'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
  'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
  'png'  => ['image/png'],  'webp' => ['image/webp'], 'gif' => ['image/gif'],
];

$attachments = [];   // for Resend: [{filename, content(base64)}]
$attachMeta  = [];   // for the email body: [{name, size}]
$f = $_FILES['attachments'] ?? null;
if ($f && is_array($f['name'])) {
  $provided = 0;
  $total = 0;
  $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
  for ($i = 0, $n = count($f['name']); $i < $n; $i++) {
    $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) continue;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) fail(400, 'That file is too large. Attachments must total 5 MB or less.');
    if ($err !== UPLOAD_ERR_OK) fail(400, 'One of your files could not be uploaded. Please try again.');

    $provided++;
    if ($provided > MAX_FILES) fail(400, 'You can attach up to ' . MAX_FILES . ' files.');

    $tmp  = $f['tmp_name'][$i];
    $orig = (string) $f['name'][$i];
    $size = (int) ($f['size'][$i] ?? 0);
    if (!is_uploaded_file($tmp)) fail(400, 'Invalid upload.');

    $total += $size;
    if ($total > MAX_TOTAL_BYTES) fail(400, 'Attachments must total 5 MB or less.');

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!isset($ALLOWED[$ext])) fail(400, 'Only documents (PDF, Word, Excel, PowerPoint) and images are allowed.');
    $mime = $finfo ? finfo_file($finfo, $tmp) : ($f['type'][$i] ?? '');
    if (!in_array($mime, $ALLOWED[$ext], true)) fail(400, 'That file type isn\'t allowed. Please attach a document or image.');

    $data = file_get_contents($tmp);
    if ($data === false) fail(400, 'Could not read one of your files.');

    // Sanitise the display/filename to a safe basename.
    $safe = preg_replace('/[^\w.\- ]+/u', '_', basename($orig));
    $safe = $safe === '' ? ('attachment.' . $ext) : $safe;

    $attachments[] = ['filename' => $safe, 'content' => base64_encode($data)];
    $attachMeta[]  = ['name' => $safe, 'size' => $size];
  }
  if ($finfo) finfo_close($finfo);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// ── Rate limit + store. DB errors are non-fatal: we still try to email so a
// transient DB issue doesn't silently drop the enquiry.
$stored = false;
try {
  // Max 5 submissions per IP per 10 minutes.
  $rl = db()->prepare('SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
  $rl->execute([$ip]);
  if ((int) $rl->fetchColumn() >= 5) {
    fail(429, 'Too many submissions. Please try again in a few minutes.');
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

// ── Compose the rich HTML email.
$esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fmtSize = fn($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';

$rows = [
  'Name'    => $name,
  'Email'   => $email,
  'Phone'   => $phone !== ''   ? $phone   : '—',
  'Company' => $company !== '' ? $company : '—',
  'Subject' => $subject !== '' ? $subject : '—',
];
$rowsHtml = '';
foreach ($rows as $label => $val) {
  $rowsHtml .= '<tr>'
    . '<td style="padding:7px 16px 7px 0;color:#6b7280;font-size:13px;vertical-align:top;white-space:nowrap;">' . $esc($label) . '</td>'
    . '<td style="padding:7px 0;color:#111827;font-size:14px;">' . $esc($val) . '</td>'
    . '</tr>';
}

$attachHtml = '';
if ($attachMeta) {
  $items = '';
  foreach ($attachMeta as $a) {
    $items .= '<li style="margin:2px 0;color:#111827;font-size:13px;">'
      . $esc($a['name']) . ' <span style="color:#9ca3af;">(' . $esc($fmtSize($a['size'])) . ')</span></li>';
  }
  $attachHtml =
    '<div style="margin-top:20px;">'
    . '<div style="font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">'
    . count($attachMeta) . ' attachment' . (count($attachMeta) === 1 ? '' : 's') . '</div>'
    . '<ul style="margin:0;padding-left:18px;">' . $items . '</ul></div>';
}

$mailSubject = 'New contact form message' . ($subject !== '' ? ': ' . $subject : '');
$html =
  '<div style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">'
  . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
    . '<div style="background:#09090b;padding:20px 28px;">'
      . '<span style="color:#f4f4f5;font-size:16px;font-weight:700;">GURU<span style="color:#a3e635;">PANDIT</span></span>'
      . '<div style="color:#9ca3af;font-size:12px;margin-top:2px;">New message from the pandit.guru contact form</div>'
    . '</div>'
    . '<div style="padding:24px 28px;">'
      . '<table style="border-collapse:collapse;width:100%;">' . $rowsHtml . '</table>'
      . '<div style="margin-top:18px;font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;">Message</div>'
      . '<div style="margin-top:6px;padding:16px 18px;background:#f9fafb;border:1px solid #eef0f2;border-radius:8px;'
        . 'white-space:pre-wrap;color:#111827;font-size:14px;line-height:1.6;">' . nl2br($esc($message)) . '</div>'
      . $attachHtml
    . '</div>'
    . '<div style="padding:14px 28px;background:#fafafa;border-top:1px solid #eef0f2;color:#9ca3af;font-size:12px;">'
      . 'Reply to this email to respond directly to ' . $esc($name) . '.'
    . '</div>'
  . '</div></div>';

[$sent, $sendErr] = resend_send(CONTACT_TO, $mailSubject, $html, $email, $attachments);
if (!$sent) {
  error_log('Contact email send failed: ' . $sendErr);
}

// Both the durable store and the email failed → real error the visitor should retry.
if (!$stored && !$sent) {
  fail(500, 'Sorry, something went wrong. Please email hello@pandit.guru directly.');
}

http_response_code(200);
exit(json_encode(['success' => true]));
