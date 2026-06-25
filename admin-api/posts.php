<?php
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_perm('content');

function slugify($text) {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text);
  return trim($text, '-') ?: 'post';
}

function unique_slug($slug, $ignoreId = null) {
  $base = $slug; $i = 2;
  while (true) {
    $stmt = db()->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ?');
    $stmt->execute([$slug, $ignoreId ?? '']);
    if (!$stmt->fetch()) return $slug;
    $slug = $base . '-' . $i; $i++;
  }
}

function row_to_post($r) {
  return [
    'id' => $r['id'], 'type' => $r['type'], 'title' => $r['title'], 'slug' => $r['slug'],
    'excerpt' => $r['excerpt'], 'body' => $r['body'], 'author' => $r['author'],
    'authorId' => $r['author_id'],
    'coverImage' => $r['cover_image'], 'tags' => $r['tags'] ? json_decode($r['tags'], true) : [],
    'status' => $r['status'], 'createdAt' => $r['created_at'], 'updatedAt' => $r['updated_at'],
    'publishedAt' => $r['published_at'],
    'metaTitle' => $r['meta_title'], 'metaDescription' => $r['meta_description'],
    'metaKeywords' => $r['meta_keywords'],
  ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Lightweight per-post engagement stats for the list view — kept as cheap
  // grouped subqueries rather than joining post_views/post_comments directly,
  // so a post with zero views/comments doesn't fan out into a slow join.
  $viewStats = [];
  foreach (db()->query('SELECT post_id, COUNT(*) AS views, AVG(seconds_spent) AS avg_seconds FROM post_views GROUP BY post_id') as $r) {
    $viewStats[$r['post_id']] = ['views' => (int) $r['views'], 'avgSeconds' => (int) round($r['avg_seconds'])];
  }
  $pendingComments = [];
  foreach (db()->query("SELECT post_id, COUNT(*) AS n FROM post_comments WHERE status IN ('pending','spam') GROUP BY post_id") as $r) {
    $pendingComments[$r['post_id']] = (int) $r['n'];
  }
  $approvedComments = [];
  foreach (db()->query("SELECT post_id, COUNT(*) AS n FROM post_comments WHERE status = 'approved' GROUP BY post_id") as $r) {
    $approvedComments[$r['post_id']] = (int) $r['n'];
  }

  $out = [];
  foreach (db()->query('SELECT * FROM posts ORDER BY created_at DESC') as $r) {
    $post = row_to_post($r);
    $post['views'] = $viewStats[$r['id']]['views'] ?? 0;
    $post['avgSecondsOnPage'] = $viewStats[$r['id']]['avgSeconds'] ?? 0;
    $post['approvedComments'] = $approvedComments[$r['id']] ?? 0;
    $post['pendingComments'] = $pendingComments[$r['id']] ?? 0;
    $out[] = $post;
  }
  json_out(['posts' => $out]);
}

$body = read_body();

if ($method === 'POST') {
  $type = ($body['type'] ?? 'blog') === 'news' ? 'news' : 'blog';
  $title = trim($body['title'] ?? '');
  if ($title === '') json_out(['error' => 'Title is required'], 400);
  $status = ($body['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
  $id = db_new_id();
  $slug = unique_slug(slugify($body['slug'] ?: $title));

  // publishedAt may be explicitly set (incl. back-dated); otherwise defaults
  // to now() only once the post is actually published.
  $publishedAt = !empty($body['publishedAt']) ? date('Y-m-d H:i:s', strtotime($body['publishedAt']))
    : ($status === 'published' ? date('Y-m-d H:i:s') : null);

  $stmt = db()->prepare(
    'INSERT INTO posts (id, type, title, slug, excerpt, body, author, author_id, cover_image, tags, status,
       meta_title, meta_description, meta_keywords, created_at, updated_at, published_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)'
  );
  $stmt->execute([
    $id, $type, $title, $slug,
    trim($body['excerpt'] ?? ''), $body['body'] ?? '', trim($body['author'] ?? 'Pandit Guru'),
    $body['authorId'] ?? null,
    trim($body['coverImage'] ?? ''), json_encode(is_array($body['tags'] ?? null) ? $body['tags'] : []),
    $status,
    trim($body['metaTitle'] ?? ''), trim($body['metaDescription'] ?? ''), trim($body['metaKeywords'] ?? ''),
    $publishedAt,
  ]);
  $row = db()->prepare('SELECT * FROM posts WHERE id = ?'); $row->execute([$id]);
  json_out(['post' => row_to_post($row->fetch())], 201);
}

if ($method === 'PUT') {
  $id = $body['id'] ?? '';
  $cur = db()->prepare('SELECT * FROM posts WHERE id = ?'); $cur->execute([$id]);
  $post = $cur->fetch();
  if (!$post) json_out(['error' => 'Post not found'], 404);

  $type = array_key_exists('type', $body)
    ? (($body['type'] === 'news') ? 'news' : 'blog') : $post['type'];
  $title = array_key_exists('title', $body) ? trim($body['title']) : $post['title'];
  $excerpt = array_key_exists('excerpt', $body) ? trim($body['excerpt']) : $post['excerpt'];
  $content = array_key_exists('body', $body) ? $body['body'] : $post['body'];
  $author = array_key_exists('author', $body) ? trim($body['author']) : $post['author'];
  $authorId = array_key_exists('authorId', $body) ? ($body['authorId'] ?: null) : $post['author_id'];
  $cover = array_key_exists('coverImage', $body) ? trim($body['coverImage']) : $post['cover_image'];
  $tags = array_key_exists('tags', $body) ? json_encode(is_array($body['tags']) ? $body['tags'] : []) : $post['tags'];
  $status = array_key_exists('status', $body)
    ? (($body['status'] === 'published') ? 'published' : 'draft') : $post['status'];
  $metaTitle = array_key_exists('metaTitle', $body) ? trim($body['metaTitle']) : $post['meta_title'];
  $metaDescription = array_key_exists('metaDescription', $body) ? trim($body['metaDescription']) : $post['meta_description'];
  $metaKeywords = array_key_exists('metaKeywords', $body) ? trim($body['metaKeywords']) : $post['meta_keywords'];

  $slug = $post['slug'];
  if (!empty($body['slug']) && trim($body['slug']) !== '') {
    $slug = unique_slug(slugify($body['slug']), $id);
  }

  // publishedAt can be explicitly edited (incl. back-dated); otherwise it's
  // set the first time the post transitions to published.
  $publishedAt = $post['published_at'];
  if (array_key_exists('publishedAt', $body) && !empty($body['publishedAt'])) {
    $publishedAt = date('Y-m-d H:i:s', strtotime($body['publishedAt']));
  } elseif ($status === 'published' && empty($publishedAt)) {
    $publishedAt = date('Y-m-d H:i:s');
  }

  $stmt = db()->prepare(
    'UPDATE posts SET type=?, title=?, slug=?, excerpt=?, body=?, author=?, author_id=?, cover_image=?, tags=?, status=?,
       meta_title=?, meta_description=?, meta_keywords=?, updated_at=NOW(), published_at=? WHERE id=?'
  );
  $stmt->execute([
    $type, $title, $slug, $excerpt, $content, $author, $authorId, $cover, $tags, $status,
    $metaTitle, $metaDescription, $metaKeywords, $publishedAt, $id,
  ]);
  $row = db()->prepare('SELECT * FROM posts WHERE id = ?'); $row->execute([$id]);
  json_out(['post' => row_to_post($row->fetch())]);
}

if ($method === 'DELETE') {
  $id = $body['id'] ?? ($_GET['id'] ?? '');
  $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
  $stmt->execute([$id]);
  json_out(['success' => true]);
}

json_out(['error' => 'Method not allowed'], 405);
