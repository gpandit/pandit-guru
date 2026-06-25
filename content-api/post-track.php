<?php
/**
 * View/dwell-time beacon for blog & news articles (public, no auth).
 * Mirrors the whitepaper doc-track.php pattern: one row per (post, session),
 * updated as the reader stays on the page. Country is resolved once, when the
 * session row is first created, not on every beacon call.
 *
 *   POST { postId, sessionKey, secondsSpent?, referrer? } → 204
 *
 * Always returns 204 — a tracking failure must never disrupt the reader.
 */

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/geo.php';

function done() { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') done();

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) { parse_str($raw, $body); } // sendBeacon may send form-encoded

$postId = (string) ($body['postId'] ?? '');
$sessionKey = substr((string) ($body['sessionKey'] ?? ''), 0, 40);
$seconds = max(0, (int) ($body['secondsSpent'] ?? 0));
$referrer = substr((string) ($body['referrer'] ?? ''), 0, 255);

if ($postId === '' || $sessionKey === '') done();

try {
  $stmt = db()->prepare('SELECT id, seconds_spent FROM post_views WHERE post_id = ? AND session_key = ? LIMIT 1');
  $stmt->execute([$postId, $sessionKey]);
  $existing = $stmt->fetch();

  if ($existing) {
    db()->prepare('UPDATE post_views SET seconds_spent = ?, updated_at = NOW() WHERE id = ?')
      ->execute([max((int) $existing['seconds_spent'], $seconds), $existing['id']]);
  } else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db()->prepare(
      'INSERT INTO post_views (id, post_id, session_key, ip, country, referrer, seconds_spent, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    )->execute([db_new_id(), $postId, $sessionKey, $ip, geo_country($ip), $referrer, $seconds]);
  }
} catch (Throwable $e) {
  error_log('post-track error: ' . $e->getMessage());
}

done();
