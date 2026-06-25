<?php
/**
 * Emoji-style reactions on blog/news posts (public, no auth). Deduped per
 * reader via a hashed voter key (ip + post, salted with BLIND_INDEX_KEY) so
 * the same visitor can't inflate counts, without storing their raw IP here.
 *
 *   GET  ?postId=<id>                  → { counts: { clap: 3, ... }, mine: ['clap'] }
 *   POST { postId, type }              → toggles that reaction for this visitor
 */

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

const REACTION_TYPES = ['clap', 'thumbs_up', 'love'];

function out($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function voter_key($postId) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $key = base64_decode(BLIND_INDEX_KEY, true) ?: '';
  return hash_hmac('sha256', $ip . '|' . $postId, $key);
}

function counts_for($postId) {
  $stmt = db()->prepare('SELECT type, COUNT(*) AS n FROM post_reactions WHERE post_id = ? GROUP BY type');
  $stmt->execute([$postId]);
  $counts = array_fill_keys(REACTION_TYPES, 0);
  foreach ($stmt as $r) { $counts[$r['type']] = (int) $r['n']; }
  return $counts;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $postId = $_GET['postId'] ?? '';
  if ($postId === '') out(['counts' => array_fill_keys(REACTION_TYPES, 0), 'mine' => []]);

  $voter = voter_key($postId);
  $mineStmt = db()->prepare('SELECT type FROM post_reactions WHERE post_id = ? AND voter_key = ?');
  $mineStmt->execute([$postId, $voter]);
  $mine = array_column($mineStmt->fetchAll(), 'type');

  out(['counts' => counts_for($postId), 'mine' => $mine]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Method not allowed'], 405);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$postId = trim($body['postId'] ?? '');
$type = trim($body['type'] ?? '');

if ($postId === '' || !in_array($type, REACTION_TYPES, true)) out(['error' => 'Invalid request'], 400);

$voter = voter_key($postId);
$existing = db()->prepare('SELECT id FROM post_reactions WHERE post_id = ? AND type = ? AND voter_key = ?');
$existing->execute([$postId, $type, $voter]);

if ($existing->fetch()) {
  db()->prepare('DELETE FROM post_reactions WHERE post_id = ? AND type = ? AND voter_key = ?')
    ->execute([$postId, $type, $voter]);
} else {
  db()->prepare('INSERT INTO post_reactions (id, post_id, type, voter_key, created_at) VALUES (?, ?, ?, ?, NOW())')
    ->execute([db_new_id(), $postId, $type, $voter]);
}

$mineStmt = db()->prepare('SELECT type FROM post_reactions WHERE post_id = ? AND voter_key = ?');
$mineStmt->execute([$postId, $voter]);
$mine = array_column($mineStmt->fetchAll(), 'type');

out(['counts' => counts_for($postId), 'mine' => $mine]);
