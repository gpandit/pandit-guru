<?php
/**
 * Admin moderation queue for reader comments.
 *   GET    ?status=pending|spam|approved|all  → list comments (default: pending+spam)
 *   PUT    { id, status }                      → approve / mark spam / reject
 *   DELETE { id }                               → permanently remove
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/crypto.php';
require __DIR__ . '/../lib/users.php';

require_perm('content');

function row_to_comment($r) {
  return [
    'id' => $r['id'], 'postId' => $r['post_id'], 'postTitle' => $r['title'] ?? null,
    'postSlug' => $r['slug'] ?? null, 'postType' => $r['type'] ?? null,
    'name' => $r['name'], 'email' => decrypt_value($r['email_enc']),
    'body' => $r['body'], 'status' => $r['status'], 'ip' => $r['ip'],
    'createdAt' => $r['created_at'],
  ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $status = $_GET['status'] ?? 'queue';
  $sql = "SELECT post_comments.*, posts.title, posts.slug, posts.type
          FROM post_comments LEFT JOIN posts ON posts.id = post_comments.post_id";
  if ($status === 'all') {
    $stmt = db()->query($sql . ' ORDER BY post_comments.created_at DESC');
  } elseif ($status === 'queue') {
    $stmt = db()->query($sql . " WHERE post_comments.status IN ('pending','spam') ORDER BY post_comments.created_at DESC");
  } else {
    $stmt = db()->prepare($sql . ' WHERE post_comments.status = ? ORDER BY post_comments.created_at DESC');
    $stmt->execute([$status]);
  }
  $out = [];
  foreach ($stmt as $r) $out[] = row_to_comment($r);
  json_out(['comments' => $out]);
}

$body = read_body();

if ($method === 'PUT') {
  $id = $body['id'] ?? '';
  $status = $body['status'] ?? '';
  if (!in_array($status, ['approved', 'pending', 'spam'], true)) json_out(['error' => 'Invalid status'], 400);
  $stmt = db()->prepare('UPDATE post_comments SET status = ? WHERE id = ?');
  $stmt->execute([$status, $id]);
  json_out(['success' => true]);
}

if ($method === 'DELETE') {
  $id = $body['id'] ?? ($_GET['id'] ?? '');
  db()->prepare('DELETE FROM post_comments WHERE id = ?')->execute([$id]);
  json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);
