<?php
/**
 * Session-based admin auth with enforced two-factor (password + TOTP).
 *
 * Session stages:
 *   (none)                          → not logged in
 *   $_SESSION['mfa_pending'] = true → password correct, awaiting TOTP
 *   $_SESSION['admin_authed'] = true → fully authenticated (password + TOTP)
 */

require_once __DIR__ . '/config.php';

// Harden the session cookie before starting the session.
if (session_status() === PHP_SESSION_NONE) {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
  ]);
  session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function json_out($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/** Fully authenticated = password AND TOTP both passed. */
function is_admin() {
  return !empty($_SESSION['admin_authed']);
}

/** Password verified but second factor still required. */
function is_mfa_pending() {
  return !empty($_SESSION['mfa_pending']);
}

function require_admin() {
  if (!is_admin()) {
    json_out(['error' => 'Unauthorized'], 401);
  }
}

function read_body() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function require_post() {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed'], 405);
  }
}

function reset_session() {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
