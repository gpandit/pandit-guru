<?php
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_post();
$body = read_body();
$email = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';

usleep(300000); // brute-force slowdown

$user = find_user_by_email($email);
$ok = $user
  && (int) $user['active'] === 1
  && !empty($user['password_hash'])
  && is_string($password)
  && password_verify($password, $user['password_hash']);

if (!$ok) {
  reset_session();
  json_out(['error' => 'Incorrect email or password'], 401);
}

// Password OK — begin the second-factor stage. Not authed until TOTP passes.
session_regenerate_id(true);
$_SESSION = [];
$_SESSION['mfa_pending'] = true;
$_SESSION['pending_user_id'] = $user['id'];

json_out([
  'success' => true,
  'mfa_required' => true,
  'mfa_enrolled' => (int) $user['mfa_enrolled'] === 1,
]);
