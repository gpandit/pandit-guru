<?php
/**
 * Admin-only user management.
 *   GET                          → list users
 *   POST { email, name, perms, isAdmin }   → create + email an invite
 *   PUT  { id, ... }             → update perms/name/active, or resend invite
 *   DELETE { id }                → deactivate (soft delete)
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_user_admin();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $out = [];
  foreach (db()->query('SELECT * FROM users ORDER BY created_at ASC') as $u) {
    $out[] = user_public($u);
  }
  json_out(['users' => $out]);
}

$body = read_body();

if ($method === 'POST') {
  $email = strtolower(trim($body['email'] ?? ''));
  $name  = trim($body['name'] ?? '');
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['error' => 'A valid email is required'], 400);
  if (find_user_by_email($email)) json_out(['error' => 'A user with that email already exists'], 409);

  $perms = $body['perms'] ?? [];
  $isAdmin = !empty($body['isAdmin']) ? 1 : 0;
  $id = db_new_id();

  $stmt = db()->prepare(
    'INSERT INTO users (id, email, name, is_admin, perm_content, active, created_at)
     VALUES (?, ?, ?, ?, ?, 1, NOW())'
  );
  $stmt->execute([
    $id, $email, $name, $isAdmin,
    !empty($perms['content']) ? 1 : 0,
  ]);

  $token = issue_reset_token($id);
  $emailed = send_reset_email($email, $name, $token, true);

  $row = db()->prepare('SELECT * FROM users WHERE id = ?'); $row->execute([$id]);
  json_out(['user' => user_public($row->fetch()), 'invited' => $emailed], 201);
}

if ($method === 'PUT') {
  $id = $body['id'] ?? '';
  $cur = db()->prepare('SELECT * FROM users WHERE id = ?'); $cur->execute([$id]);
  $user = $cur->fetch();
  if (!$user) json_out(['error' => 'User not found'], 404);

  // Resend invite / reset link.
  if (!empty($body['resendInvite'])) {
    $token = issue_reset_token($id);
    $emailed = send_reset_email($user['email'], $user['name'], $token, empty($user['password_hash']));
    json_out(['success' => true, 'invited' => $emailed]);
  }

  $me = current_user();
  $perms = $body['perms'] ?? null;
  $name = array_key_exists('name', $body) ? trim($body['name']) : $user['name'];
  $active = array_key_exists('active', $body) ? (!empty($body['active']) ? 1 : 0) : (int) $user['active'];
  $isAdmin = array_key_exists('isAdmin', $body) ? (!empty($body['isAdmin']) ? 1 : 0) : (int) $user['is_admin'];

  // Guard: don't let an admin strip their own admin rights or deactivate self.
  if ($me && $me['id'] === $id) { $isAdmin = 1; $active = 1; }

  $pc = $perms !== null ? (!empty($perms['content']) ? 1 : 0) : (int) $user['perm_content'];

  db()->prepare(
    'UPDATE users SET name=?, is_admin=?, perm_content=?, active=? WHERE id=?'
  )->execute([$name, $isAdmin, $pc, $active, $id]);

  $row = db()->prepare('SELECT * FROM users WHERE id = ?'); $row->execute([$id]);
  json_out(['user' => user_public($row->fetch())]);
}

if ($method === 'DELETE') {
  $id = $body['id'] ?? ($_GET['id'] ?? '');
  $me = current_user();
  if ($me && $me['id'] === $id) json_out(['error' => 'You cannot deactivate your own account'], 400);
  db()->prepare('UPDATE users SET active = 0 WHERE id = ?')->execute([$id]);
  json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);
