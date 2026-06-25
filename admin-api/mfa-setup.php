<?php
/**
 * First-time authenticator enrolment for the pending user.
 *   GET  → returns a fresh secret + otpauth URI to render as a QR code.
 *   POST { code } → verifies, persists the encrypted secret, completes login.
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/crypto.php';
require __DIR__ . '/../lib/totp.php';

$uid = $_SESSION['pending_user_id'] ?? null;
if (!is_mfa_pending() || !$uid) {
  json_out(['error' => 'Unauthorized'], 401);
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) json_out(['error' => 'Unauthorized'], 401);
if ((int) $user['mfa_enrolled'] === 1) {
  json_out(['error' => 'Authenticator already enrolled'], 409);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (empty($_SESSION['totp_setup_secret'])) {
    $_SESSION['totp_setup_secret'] = totp_generate_secret();
  }
  $secret = $_SESSION['totp_setup_secret'];
  json_out([
    'secret' => $secret,
    'otpauth_uri' => totp_provisioning_uri($secret, $user['email'], MFA_ISSUER),
  ]);
}

require_post();
$body = read_body();
$secret = $_SESSION['totp_setup_secret'] ?? '';
if ($secret === '') json_out(['error' => 'No enrolment in progress'], 400);
if (!totp_verify($secret, $body['code'] ?? '')) {
  json_out(['error' => 'Incorrect code — try again'], 401);
}

db()->prepare('UPDATE users SET totp_secret_enc = ?, mfa_enrolled = 1 WHERE id = ?')
   ->execute([encrypt_value($secret), $uid]);

unset($_SESSION['totp_setup_secret'], $_SESSION['mfa_pending'], $_SESSION['pending_user_id']);
session_regenerate_id(true);
$_SESSION['admin_authed'] = true;
$_SESSION['user_id'] = $uid;

json_out(['success' => true]);
