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

// ════════ RESEND (all transactional email: contact form + admin invite/reset) ════════
// Email is sent via Resend's HTTP API. RESEND_FROM must use a domain verified
// in your Resend account (pandit.guru). CONTACT_TO is where contact-form
// submissions are delivered; admin reset/invite mail goes to the admin's own
// address.
define('RESEND_API_KEY', env_value('RESEND_API_KEY'));
define('RESEND_FROM', env_value('RESEND_FROM', 'Pandit Guru Website <website@pandit.guru>'));
define('CONTACT_TO', env_value('CONTACT_TO', 'hello@pandit.guru'));

// ════════ reCAPTCHA v3 (optional contact-form spam protection) ════════
// When both keys are set, the contact form runs an invisible reCAPTCHA v3
// check and rejects low-scoring (bot-like) submissions. Leave blank to
// disable — the form then works with no captcha. Site key is public; secret
// stays server-side.
define('RECAPTCHA_SITE_KEY', env_value('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET', env_value('RECAPTCHA_SECRET'));
define('RECAPTCHA_MIN_SCORE', (float) env_value('RECAPTCHA_MIN_SCORE', '0.5'));

// ════════ STARTUP VALIDATION ════════
// Catches misconfigured deployments early with a clear error_log message
// instead of a cryptic PDO/sodium exception that returns a generic 500.
(function () {
  $required = [
    'DB_NAME', 'DB_USER', 'DB_PASS', 'ENCRYPTION_KEY', 'BLIND_INDEX_KEY',
    'ADMIN_PASSWORD_HASH',
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
