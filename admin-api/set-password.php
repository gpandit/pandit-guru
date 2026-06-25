<?php
/**
 * Public endpoint: set a password using a valid reset/invite token.
 *   GET  ?token=...        → { valid, email } (to render the form)
 *   POST { token, password } → sets the password, clears the token
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $token = $_GET['token'] ?? '';
  $user = user_by_reset_token($token);
  // `reason` is a non-sensitive diagnostic (hash missing / expired / inactive)
  // so the cause is visible in the browser, not just the server error log.
  if (!$user) json_out(['valid' => false, 'reason' => reset_token_diagnosis($token)], 200);
  json_out(['valid' => true, 'email' => $user['email']]);
}

require_post();
$body = read_body();
$token = $body['token'] ?? '';
$password = $body['password'] ?? '';

if (!is_string($password) || strlen($password) < 10) {
  json_out(['error' => 'Password must be at least 10 characters'], 400);
}

$user = user_by_reset_token($token);
if (!$user) {
  json_out(['error' => 'This link is invalid or has expired'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
clear_reset_token($user['id']);

json_out(['success' => true]);
