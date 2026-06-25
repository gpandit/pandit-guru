<?php
/**
 * Blog/news view analytics: per-post summary, and a raw per-session log
 * (one row per reader: IP, country, referrer, seconds spent, timestamps).
 *
 *   GET ?summary=1                    -> all posts with view/avg-time/unique-IP totals
 *   GET ?postId=...                   -> raw post_views rows for that post (JSON)
 *   GET ?postId=...&format=csv        -> same rows as a CSV download
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_perm('content');

$postId = (string) ($_GET['postId'] ?? '');

if ($postId === '') {
  $rows = [];
  $stmt = db()->query(
    "SELECT p.id, p.title, p.slug, p.type, p.status,
            COUNT(v.id) AS views,
            COALESCE(AVG(v.seconds_spent), 0) AS avg_seconds,
            COUNT(DISTINCT v.ip) AS unique_ips
       FROM posts p
       LEFT JOIN post_views v ON v.post_id = p.id
      GROUP BY p.id, p.title, p.slug, p.type, p.status
      ORDER BY views DESC, p.created_at DESC"
  );
  foreach ($stmt as $r) {
    $rows[] = [
      'id' => $r['id'], 'title' => $r['title'], 'slug' => $r['slug'],
      'type' => $r['type'], 'status' => $r['status'],
      'views' => (int) $r['views'],
      'avgSecondsOnPage' => (int) round($r['avg_seconds']),
      'uniqueIps' => (int) $r['unique_ips'],
    ];
  }
  json_out(['posts' => $rows]);
  exit;
}

$stmt = db()->prepare(
  'SELECT v.*, p.title AS post_title FROM post_views v
   JOIN posts p ON p.id = v.post_id
   WHERE v.post_id = ? ORDER BY v.updated_at DESC'
);
$stmt->execute([$postId]);
$views = $stmt->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
  $title = $views[0]['post_title'] ?? $postId;
  $filename = preg_replace('/[^a-z0-9\-]/i', '-', $title) . '-views.csv';
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['IP', 'Country', 'Seconds Spent', 'Referrer', 'First Viewed', 'Last Updated']);
  foreach ($views as $v) {
    fputcsv($out, [$v['ip'], $v['country'], $v['seconds_spent'], $v['referrer'], $v['created_at'], $v['updated_at']]);
  }
  fclose($out);
  exit;
}

json_out([
  'postId' => $postId,
  'postTitle' => $views[0]['post_title'] ?? null,
  'views' => array_map(function ($v) {
    return [
      'ip' => $v['ip'], 'country' => $v['country'], 'referrer' => $v['referrer'],
      'secondsSpent' => (int) $v['seconds_spent'],
      'createdAt' => $v['created_at'], 'updatedAt' => $v['updated_at'],
    ];
  }, $views),
]);
