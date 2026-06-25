<?php
/**
 * Shared image storage helpers: downscale/recompress an uploaded file with
 * GD, store the blob in the `images` table, and build the public serve URL.
 */

require_once __DIR__ . '/db.php';

const IMAGE_MAX_DIMENSION = 1600; // px, longest edge
const IMAGE_JPEG_QUALITY  = 82;

/** Public URL for a stored image id. */
function image_url($id) {
  return '/image.php?id=' . rawurlencode($id);
}

/**
 * Load, downscale (if needed) and recompress an uploaded file, then store it.
 * Returns ['id' => ..., 'width' => ..., 'height' => ...]. Throws on a file
 * GD can't decode.
 */
function image_store_from_path($path, $alt = '', $postId = null) {
  if (!extension_loaded('gd')) {
    throw new RuntimeException('Image processing is unavailable on this server (GD extension missing).');
  }

  $info = @getimagesize($path);
  if (!$info) throw new RuntimeException('Could not read this file as an image.');
  [$origW, $origH, $type] = $info;

  switch ($type) {
    case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
    case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path); break;
    case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false; break;
    case IMAGETYPE_GIF:  $src = @imagecreatefromgif($path); break;
    default: $src = false;
  }
  if (!$src) throw new RuntimeException('Unsupported image format. Use JPEG, PNG, WebP or GIF.');

  $scale = min(1, IMAGE_MAX_DIMENSION / max($origW, $origH));
  $w = max(1, (int) round($origW * $scale));
  $h = max(1, (int) round($origH * $scale));

  $hasAlpha = in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
  $dst = imagecreatetruecolor($w, $h);
  if ($hasAlpha) {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
  }
  imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $origW, $origH);
  imagedestroy($src);

  ob_start();
  if ($hasAlpha) {
    imagepng($dst);
    $mime = 'image/png';
  } else {
    imagejpeg($dst, null, IMAGE_JPEG_QUALITY);
    $mime = 'image/jpeg';
  }
  $bytes = ob_get_clean();
  imagedestroy($dst);

  $id = db_new_id();
  db()->prepare(
    'INSERT INTO images (id, mime, bytes, alt, width, height, post_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
  )->execute([$id, $mime, $bytes, $alt, $w, $h, $postId]);

  return ['id' => $id, 'width' => $w, 'height' => $h];
}
