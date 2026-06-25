<?php
/**
 * Central configuration for the pandit.guru News & Blog backend.
 *
 * Every secret/per-environment value is read via getenv() — this file is
 * identical on every branch/environment and carries no secrets, so a
 * staging<->main merge never touches or conflicts over credentials.
 *
 * On a server where you control the process environment directly (e.g. a
 * VPS running this under systemd or Docker), set these as real process
 * environment variables and skip the .env file entirely. On shared hosting
 * (Hostinger) where there's no panel control over the PHP process
 * environment, env.php loads them from a gitignored .env file instead (see
 * .env.example) — the getenv() calls below work unchanged either way.
 */

require_once __DIR__ . '/env.php';
load_env_file(__DIR__ . '/.env');

function env_value($key, $default = '') {
  $v = getenv($key);
  return ($v === false || $v === '') ? $default : $v;
}

// ════════ SITE ════════
define('SITE_NAME', env_value('SITE_NAME', 'Pandit Guru'));
define('SITE_URL', env_value('SITE_URL', 'https://pandit.guru'));   // used for unsubscribe links + MFA issuer
define('DEBUG_ERRORS', env_value('DEBUG_ERRORS', 'false') === 'true'); // keep false in production

// ════════ ADMIN AUTH ════════
define('ADMIN_PASSWORD_HASH', env_value('ADMIN_PASSWORD_HASH'));
define('ADMIN_ACCOUNT', env_value('ADMIN_ACCOUNT', 'admin@pandit.guru')); // label shown in the authenticator app
define('MFA_ISSUER', env_value('MFA_ISSUER', 'Pandit Guru Admin'));

// ════════ DATABASE (Hostinger MySQL / MariaDB) ════════
// Create an empty database + user in hPanel → Databases, then set DB_* via
// environment variables (or .env on this server). Tables are created
// automatically on first connection — no schema/SQL file to import.
define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_NAME', env_value('DB_NAME'));
define('DB_USER', env_value('DB_USER'));
define('DB_PASS', env_value('DB_PASS'));
define('DB_CHARSET', env_value('DB_CHARSET', 'utf8mb4'));

// ════════ ENCRYPTION KEYS ════════
// WARNING: if ENCRYPTION_KEY is lost, encrypted data is unrecoverable.
define('ENCRYPTION_KEY', env_value('ENCRYPTION_KEY'));
define('BLIND_INDEX_KEY', env_value('BLIND_INDEX_KEY'));

// ════════ SMTP (admin invite/reset emails) ════════
define('MAIL_HOST', env_value('MAIL_HOST', 'smtp-pulse.com'));
define('MAIL_PORT', (int) env_value('MAIL_PORT', '465'));      // 465 = SSL, 587 = TLS
define('MAIL_USER', env_value('MAIL_USER'));
define('MAIL_PASS', env_value('MAIL_PASS'));
define('MAIL_FROM', env_value('MAIL_FROM', 'hello@pandit.guru'));
define('MAIL_FROM_NAME', env_value('MAIL_FROM_NAME', 'Pandit Guru'));

// ════════ STARTUP VALIDATION ════════
// Catches misconfigured deployments early with a clear error_log message
// instead of a cryptic PDO/sodium exception that returns a generic 500.
(function () {
  $required = [
    'DB_NAME', 'DB_USER', 'DB_PASS', 'ENCRYPTION_KEY', 'BLIND_INDEX_KEY',
    'MAIL_USER', 'MAIL_PASS', 'ADMIN_PASSWORD_HASH',
  ];
  $missing = [];
  foreach ($required as $const) {
    if (!defined($const) || constant($const) === '') $missing[] = $const;
  }
  // Validate key lengths so a truncated/wrong key is caught before sodium throws.
  if (!in_array('ENCRYPTION_KEY', $missing, true)) {
    $k = base64_decode(ENCRYPTION_KEY, true);
    if ($k === false || strlen($k) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      $missing[] = 'ENCRYPTION_KEY (invalid base64 or wrong length — expected 32 bytes)';
    }
  }
  if (!in_array('BLIND_INDEX_KEY', $missing, true)) {
    $k = base64_decode(BLIND_INDEX_KEY, true);
    if ($k === false || strlen($k) < 16) {
      $missing[] = 'BLIND_INDEX_KEY (invalid base64)';
    }
  }
  if ($missing) {
    $msg = 'CONFIG ERROR — the following environment variables are missing or invalid: '
      . implode(', ', $missing)
      . '. Set them as real environment variables, or via lib/.env on this server (copy lib/.env.example).';
    error_log($msg);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => DEBUG_ERRORS ? $msg : 'Server configuration error. Check the PHP error log.']);
    exit;
  }
})();
