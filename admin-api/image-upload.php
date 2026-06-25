<?php
/**
 * Admin image upload — used by the post editor (cover image, inline images,
 * author avatars). Stores the resized/recompressed bytes in the `images`
 * table so uploads survive a git-deploy and work identically on every host
 * (no server-side writable directory required).
 *
 *   POST multipart/form-data { file, postId? (optional, informational), alt? }
 *   → { id, url, width, height }
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';
require __DIR__ . '/../lib/images.php';

require_perm('content');
require_post();

if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  json_out(['error' => 'An image file is required'], 400);
}

$file = $_FILES['file'];
if ($file['size'] > 10 * 1024 * 1024) {
  json_out(['error' => 'Image must be 10MB or smaller'], 400);
}

try {
  $img = image_store_from_path($file['tmp_name'], trim($_POST['alt'] ?? ''), trim($_POST['postId'] ?? '') ?: null);
} catch (Throwable $e) {
  error_log('image-upload error: ' . $e->getMessage());
  json_out(['error' => $e->getMessage() ?: 'Could not process this image'], 400);
}

json_out(['id' => $img['id'], 'url' => image_url($img['id']), 'width' => $img['width'], 'height' => $img['height']], 201);
