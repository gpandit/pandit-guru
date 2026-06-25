<?php
/**
 * Public, read-only image serve endpoint for DB-stored images (post covers,
 * inline body images, author avatars). Long cache lifetime since the blob
 * never changes once uploaded — a new upload always gets a new id.
 *
 *   GET /image.php?id=<imageId>
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';

$id = $_GET['id'] ?? '';
$stmt = db()->prepare('SELECT mime, bytes FROM images WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Image not found.');
}

$etag = '"' . md5($row['bytes']) . '"';
header('Content-Type: ' . $row['mime']);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
  http_response_code(304);
  exit;
}

header('Content-Length: ' . strlen($row['bytes']));
echo $row['bytes'];
