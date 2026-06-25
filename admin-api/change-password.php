<?php
/**
 * Authenticated user changes their own password.
 *   POST { current, password }
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_admin();
require_post();

$u = current_user();
if (!$u) json_out(['error' => 'Unauthorized'], 401);

$body = read_body();
$current = $body['current'] ?? '';
$password = $body['password'] ?? '';

if (empty($u['password_hash']) || !password_verify($current, $u['password_hash'])) {
  json_out(['error' => 'Current password is incorrect'], 401);
}
if (!is_string($password) || strlen($password) < 10) {
  json_out(['error' => 'New password must be at least 10 characters'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $u['id']]);

json_out(['success' => true]);
