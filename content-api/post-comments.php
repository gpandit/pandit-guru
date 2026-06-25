<?php
/**
 * Public comments on blog/news posts. No login — name + email required
 * (email encrypted at rest, like leads). Submitted comments are screened by
 * moderate_comment(): clean ones publish instantly, flagged ones are held
 * for review in the admin queue (never silently discarded).
 *
 *   GET  ?postId=<id>                 → approved comments for a post
 *   POST { postId, name, email, body, website? } → submit a comment
 *     `website` is a honeypot field — real readers never fill it in.
 */

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/crypto.php';
require __DIR__ . '/../lib/comment_moderation.php';

header('Content-Type: application/json');

function out($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $postId = $_GET['postId'] ?? '';
  if ($postId === '') out(['comments' => []]);
  $stmt = db()->prepare("SELECT id, name, body, created_at FROM post_comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC");
  $stmt->execute([$postId]);
  $list = [];
  foreach ($stmt as $r) {
    $list[] = ['id' => $r['id'], 'name' => $r['name'], 'body' => $r['body'], 'createdAt' => $r['created_at']];
  }
  out(['comments' => $list]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Method not allowed'], 405);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) out(['error' => 'Invalid request'], 400);

// Honeypot: a real reader never sees or fills this field. Pretend success so
// the bot doesn't learn its submission was rejected.
if (!empty($body['website'])) out(['success' => true]);

$postId = trim($body['postId'] ?? '');
$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$commentBody = trim($body['body'] ?? '');

if ($postId === '') out(['error' => 'Missing post'], 400);
if ($name === '') out(['error' => 'Name is required'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'A valid email is required'], 400);
if ($commentBody === '' || mb_strlen($commentBody) > 3000) out(['error' => 'Comment must be 1–3000 characters'], 400);

$post = db()->prepare('SELECT id, title, tags FROM posts WHERE id = ?');
$post->execute([$postId]);
$postRow = $post->fetch();
if (!$postRow) out(['error' => 'Post not found'], 404);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Rate-limit: 5 comments per IP per hour, across any post.
$rl = db()->prepare("SELECT COUNT(*) FROM post_comments WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$rl->execute([$ip]);
if ((int) $rl->fetchColumn() >= 5) {
  out(['error' => "You're commenting a lot — please wait a bit before posting again."], 429);
}

$decision = moderate_comment($commentBody, $postRow);

$id = db_new_id();
db()->prepare(
  'INSERT INTO post_comments (id, post_id, name, email_bi, email_enc, body, status, ip, created_at)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
)->execute([
  $id, $postId, $name, blind_index($email), encrypt_value($email),
  $commentBody, $decision['status'], $ip,
]);

if ($decision['status'] === 'approved') {
  out(['success' => true, 'status' => 'approved', 'comment' => ['id' => $id, 'name' => $name, 'body' => $commentBody, 'createdAt' => date('c')]], 201);
}

out(['success' => true, 'status' => 'pending', 'message' => "Thanks — your comment is awaiting moderation."], 201);
