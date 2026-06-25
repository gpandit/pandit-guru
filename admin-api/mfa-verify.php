<?php
/**
 * Second-factor verification for an already-enrolled pending user.
 *   POST { code }
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/crypto.php';
require __DIR__ . '/../lib/totp.php';

require_post();

$uid = $_SESSION['pending_user_id'] ?? null;
if (!is_mfa_pending() || !$uid) {
  json_out(['error' => 'Password verification required first'], 401);
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user || empty($user['totp_secret_enc'])) {
  json_out(['error' => 'No authenticator enrolled'], 409);
}

usleep(300000);

$body = read_body();
if (!totp_verify(decrypt_value($user['totp_secret_enc']), $body['code'] ?? '')) {
  json_out(['error' => 'Incorrect code'], 401);
}

unset($_SESSION['mfa_pending'], $_SESSION['pending_user_id']);
session_regenerate_id(true);
$_SESSION['admin_authed'] = true;
$_SESSION['user_id'] = $uid;

json_out(['success' => true]);
