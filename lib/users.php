<?php
/**
 * User account helpers: current user lookup, permission checks, and
 * password-reset / invite token handling.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

/** The fully-authenticated user row, or null. */
function current_user() {
  static $cached = false;
  if ($cached !== false) return $cached;
  if (!is_admin() || empty($_SESSION['user_id'])) return $cached = null;
  $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
  $stmt->execute([$_SESSION['user_id']]);
  return $cached = ($stmt->fetch() ?: null);
}

/** Does the current user hold a capability? Admins implicitly hold all. */
function has_perm($perm) {
  $u = current_user();
  if (!$u) return false;
  if ((int) $u['is_admin'] === 1) return true;
  $col = 'perm_' . $perm;
  return isset($u[$col]) && (int) $u[$col] === 1;
}

/** Require full auth AND a specific capability. */
function require_perm($perm) {
  require_admin();
  if (!has_perm($perm)) {
    json_out(['error' => 'Forbidden'], 403);
  }
}

/** Require the current user to be an admin (user management). */
function require_user_admin() {
  require_admin();
  $u = current_user();
  if (!$u || (int) $u['is_admin'] !== 1) {
    json_out(['error' => 'Forbidden'], 403);
  }
}

/** A public-safe representation of a user. */
function user_public($u) {
  return [
    'id' => $u['id'],
    'email' => $u['email'],
    'name' => $u['name'],
    'isAdmin' => (int) $u['is_admin'] === 1,
    'perms' => [
      'content' => (int) $u['perm_content'] === 1,
    ],
    'active' => (int) $u['active'] === 1,
    'mfaEnrolled' => (int) $u['mfa_enrolled'] === 1,
    'passwordSet' => !empty($u['password_hash']),
    'createdAt' => $u['created_at'],
  ];
}

function find_user_by_email($email) {
  $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
  $stmt->execute([strtolower(trim($email))]);
  return $stmt->fetch() ?: null;
}

/**
 * Create a one-time reset/invite token for a user, store its hash, and return
 * the raw token (to embed in an emailed link). Valid for 24h.
 */
function issue_reset_token($userId) {
  $token = bin2hex(random_bytes(32));
  $hash = hash('sha256', $token);
  $stmt = db()->prepare('UPDATE users SET reset_token_hash = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?');
  $stmt->execute([$hash, $userId]);
  return $token;
}

/** Look up a user by a raw (unexpired) reset token. */
function user_by_reset_token($token) {
  if (!is_string($token) || strlen($token) < 32) return null;
  $hash = hash('sha256', $token);
  $stmt = db()->prepare('SELECT * FROM users WHERE reset_token_hash = ? AND reset_expires > NOW() AND active = 1');
  $stmt->execute([$hash]);
  $user = $stmt->fetch() ?: null;
  if (!$user) {
    error_log('reset-token lookup failed: ' . reset_token_diagnosis($token) . ' (hash ' . substr($hash, 0, 8) . '…)');
  }
  return $user;
}

/**
 * Explain why a token lookup failed: hash absent (truncation / overwrite /
 * never stored), present-but-expired, or the account is inactive. Used for both
 * logging and the diagnostic field in the set-password response.
 */
function reset_token_diagnosis($token) {
  if (!is_string($token) || strlen($token) < 32) return 'malformed token (' . strlen((string) $token) . ' chars)';
  $hash = hash('sha256', $token);
  $d = db()->prepare('SELECT active, reset_expires, (reset_expires > NOW()) AS unexpired FROM users WHERE reset_token_hash = ?');
  $d->execute([$hash]);
  $row = $d->fetch();
  if (!$row) return 'no matching hash (token never stored, truncated, or overwritten by a newer request)';
  if ((int) $row['unexpired'] !== 1) return 'token expired (expires ' . ($row['reset_expires'] ?? '?') . ')';
  if ((int) $row['active'] !== 1) return 'account inactive';
  return 'ok';
}

function clear_reset_token($userId) {
  db()->prepare('UPDATE users SET reset_token_hash = NULL, reset_expires = NULL WHERE id = ?')->execute([$userId]);
}

/** Send a password-set / reset email containing the tokenised link. */
function send_reset_email($email, $name, $token, $isInvite) {
  require_once __DIR__ . '/mailer.php';
  $link = rtrim(SITE_URL, '/') . '/admin/set-password?token=' . urlencode($token);
  $subject = $isInvite ? (SITE_NAME . ' — set up your admin account')
                       : (SITE_NAME . ' — reset your password');
  $intro = $isInvite
    ? "An admin account has been created for you. Click below to set your password and then set up two-factor authentication."
    : "We received a request to reset your password. Click below to choose a new one. If you didn't request this, you can ignore this email.";

  $html = '<div style="font-family:sans-serif;max-width:520px">'
    . '<h2 style="color:#075079">' . htmlspecialchars(SITE_NAME) . ' Admin</h2>'
    . '<p>Hi ' . htmlspecialchars($name ?: '') . ',</p>'
    . '<p>' . $intro . '</p>'
    . '<p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#2caae2;color:#05202f;'
    . 'padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600">'
    . ($isInvite ? 'Set my password' : 'Reset my password') . '</a></p>'
    . '<p style="color:#888;font-size:12px">This link expires in 24 hours.</p></div>';

  try {
    $mail = make_mailer();
    $mail->addAddress($email, $name ?: $email);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $html;
    $mail->AltBody = $intro . "\n\n" . $link;
    $mail->send();
    return true;
  } catch (Throwable $e) {
    error_log('reset email error: ' . $e->getMessage());
    return false;
  }
}
