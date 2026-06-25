<?php
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';
require __DIR__ . '/../lib/images.php';

require_perm('content');

function row_to_author($r) {
  return [
    'id' => $r['id'], 'name' => $r['name'], 'bio' => $r['bio'],
    'avatarImageId' => $r['avatar_image_id'],
    'avatarUrl' => $r['avatar_image_id'] ? image_url($r['avatar_image_id']) : null,
    'createdAt' => $r['created_at'], 'updatedAt' => $r['updated_at'],
  ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $out = [];
  foreach (db()->query('SELECT * FROM authors ORDER BY name ASC') as $r) {
    $out[] = row_to_author($r);
  }
  json_out(['authors' => $out]);
}

$body = read_body();

if ($method === 'POST') {
  $name = trim($body['name'] ?? '');
  if ($name === '') json_out(['error' => 'Name is required'], 400);
  $id = db_new_id();
  db()->prepare(
    'INSERT INTO authors (id, name, bio, avatar_image_id, created_at, updated_at)
     VALUES (?, ?, ?, ?, NOW(), NOW())'
  )->execute([$id, $name, trim($body['bio'] ?? ''), $body['avatarImageId'] ?? null]);
  $row = db()->prepare('SELECT * FROM authors WHERE id = ?'); $row->execute([$id]);
  json_out(['author' => row_to_author($row->fetch())], 201);
}

if ($method === 'PUT') {
  $id = $body['id'] ?? '';
  $cur = db()->prepare('SELECT * FROM authors WHERE id = ?'); $cur->execute([$id]);
  $author = $cur->fetch();
  if (!$author) json_out(['error' => 'Author not found'], 404);

  $name = array_key_exists('name', $body) ? trim($body['name']) : $author['name'];
  if ($name === '') json_out(['error' => 'Name is required'], 400);
  $bio = array_key_exists('bio', $body) ? trim($body['bio']) : $author['bio'];
  $avatar = array_key_exists('avatarImageId', $body) ? ($body['avatarImageId'] ?: null) : $author['avatar_image_id'];

  db()->prepare('UPDATE authors SET name=?, bio=?, avatar_image_id=?, updated_at=NOW() WHERE id=?')
    ->execute([$name, $bio, $avatar, $id]);
  $row = db()->prepare('SELECT * FROM authors WHERE id = ?'); $row->execute([$id]);
  json_out(['author' => row_to_author($row->fetch())]);
}

if ($method === 'DELETE') {
  $id = $body['id'] ?? ($_GET['id'] ?? '');
  // Detach rather than block — posts keep their legacy `author` text as a
  // display fallback if the linked author is removed.
  db()->prepare('UPDATE posts SET author_id = NULL WHERE author_id = ?')->execute([$id]);
  db()->prepare('DELETE FROM authors WHERE id = ?')->execute([$id]);
  json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);
