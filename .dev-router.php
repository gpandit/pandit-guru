<?php
// Local-dev-only router for `php -S`, mirroring the .htaccess rewrite so the
// News & Blog SPA can be previewed without a real Apache server. Not deployed.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = __DIR__ . $uri;

if (preg_match('#^/(news-blog|admin)(/.*)?$#', $uri) && !is_file($path)) {
  require __DIR__ . '/blog-app.html';
  return true;
}

if ($uri !== '/' && file_exists($path) && !is_dir($path)) {
  return false; // let the built-in server handle real files (php-S serves php files for us)
}

return false;
