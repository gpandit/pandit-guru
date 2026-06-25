<?php
/**
 * Minimal, dependency-free .env loader.
 *
 * Populates getenv()/$_ENV from a KEY=value file, WITHOUT overriding any
 * variable that's already set in the real process environment. That means
 * once this app runs somewhere that injects true environment variables (a
 * VPS under systemd or Docker, for example), the .env file becomes
 * unnecessary — env.php simply skips any key the real environment already
 * provides, and config.php's getenv() calls work unchanged either way.
 *
 * Intentionally tiny and dependency-free rather than pulling in a Composer
 * package for ~25 lines of logic.
 */

function load_env_file($path) {
  if (!is_file($path) || !is_readable($path)) return;

  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;

    $eq = strpos($line, '=');
    if ($eq === false) continue;

    $key = trim(substr($line, 0, $eq));
    $value = trim(substr($line, $eq + 1));
    $len = strlen($value);
    // Strip a single layer of matching quotes, if present.
    if ($len >= 2 && (
      ($value[0] === '"' && $value[$len - 1] === '"') ||
      ($value[0] === "'" && $value[$len - 1] === "'")
    )) {
      $value = substr($value, 1, -1);
    }

    // Real environment variables always win — never override one that's
    // already set by the hosting platform's own mechanism.
    if (getenv($key) !== false) continue;

    putenv("$key=$value");
    $_ENV[$key] = $value;
  }
}
