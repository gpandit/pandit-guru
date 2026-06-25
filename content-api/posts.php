<?php
/**
 * Public, read-only endpoint serving PUBLISHED blog & news posts from MySQL.
 *   GET content-api/posts.php?type=blog            → list published blogs
 *   GET content-api/posts.php?type=news            → list published news
 *   GET content-api/posts.php?type=blog&slug=foo   → single published post
 */

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/images.php';

header('Content-Type: application/json');

$type = ($_GET['type'] ?? '') === 'news' ? 'news' : (($_GET['type'] ?? '') === 'blog' ? 'blog' : '');
$slug = trim($_GET['slug'] ?? '');

function pub_row($r, $withBody) {
  $wordCount = $withBody ? str_word_count((string) preg_replace('/<[^>]*>/', ' ', $r['body'] ?? '')) : null;
  $p = [
    'id' => $r['id'], 'type' => $r['type'], 'title' => $r['title'], 'slug' => $r['slug'],
    'excerpt' => $r['excerpt'], 'author' => $r['author_name'] ?: $r['author'],
    'authorBio' => $r['author_bio'], 'authorAvatarUrl' => $r['author_avatar_id'] ? image_url($r['author_avatar_id']) : null,
    'coverImage' => $r['cover_image'],
    'tags' => $r['tags'] ? json_decode($r['tags'], true) : [],
    'publishedAt' => $r['published_at'],
    'metaTitle' => $r['meta_title'], 'metaDescription' => $r['meta_description'], 'metaKeywords' => $r['meta_keywords'],
  ];
  if ($withBody) {
    $p['body'] = $r['body'];
    // ~220 wpm average adult silent-reading speed; rounded up, min 1.
    $p['readingTimeMinutes'] = max(1, (int) ceil($wordCount / 220));
  }
  return $p;
}

const POST_SELECT = "SELECT posts.*, authors.name AS author_name, authors.bio AS author_bio,
  authors.avatar_image_id AS author_avatar_id
  FROM posts LEFT JOIN authors ON authors.id = posts.author_id";

try {
  if ($slug !== '') {
    $stmt = db()->prepare(POST_SELECT . " WHERE posts.status='published' AND posts.slug=? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit(json_encode(['error' => 'Not found'])); }
    exit(json_encode(['post' => pub_row($row, true)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  if ($type !== '') {
    $stmt = db()->prepare(POST_SELECT . " WHERE posts.status='published' AND posts.type=? ORDER BY posts.published_at DESC");
    $stmt->execute([$type]);
  } else {
    $stmt = db()->query(POST_SELECT . " WHERE posts.status='published' ORDER BY posts.published_at DESC");
  }
  $list = [];
  foreach ($stmt as $r) $list[] = pub_row($r, false);
  exit(json_encode(['posts' => $list], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

} catch (Throwable $e) {
  error_log('content posts error: ' . $e->getMessage());
  http_response_code(500);
  exit(json_encode(['error' => 'Server error']));
}
