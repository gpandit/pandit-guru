<?php
/**
 * Inline lead-capture form shown on every blog/news article (regardless of
 * read/scroll engagement). Stores an encrypted lead, same as the contact and
 * whitepaper forms — source='blog', service holds the originating post slug
 * so leads can be traced back to the article that generated them.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/crypto.php';
require __DIR__ . '/lib/geo.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit(json_encode(['error' => 'Method not allowed']));
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$postSlug = trim($body['postSlug'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  exit(json_encode(['error' => 'Valid email required']));
}
if (empty($name)) {
  http_response_code(400);
  exit(json_encode(['error' => 'Name required']));
}

try {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  db()->prepare(
    'INSERT INTO leads (id, source, name_enc, email_bi, email_enc, service, ip, country, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
  )->execute([
    db_new_id(), 'blog',
    encrypt_value($name), blind_index($email), encrypt_value($email),
    $postSlug, $ip, geo_country($ip),
  ]);
} catch (Throwable $e) {
  error_log('Blog lead store error: ' . $e->getMessage());
  http_response_code(500);
  exit(json_encode(['error' => 'Could not save your details. Please try again.']));
}

http_response_code(200);
exit(json_encode(['success' => true]));
