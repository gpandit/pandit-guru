<?php
/**
 * Public endpoint: request a password-reset email. Always returns success to
 * avoid leaking which emails have accounts.
 *   POST { email }
 */

require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

require_post();
$body = read_body();
$email = strtolower(trim($body['email'] ?? ''));

usleep(300000);

// Always respond success to avoid leaking which emails have accounts — and so a
// transient DB/SMTP error never surfaces as a 500 to the user. Failures are logged.
try {
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $user = find_user_by_email($email);
    if ($user && (int) $user['active'] === 1) {
      $token = issue_reset_token($user['id']);
      send_reset_email($user['email'], $user['name'], $token, false);
    }
  }
} catch (Throwable $e) {
  error_log('request-reset error: ' . $e->getMessage());
}

json_out(['success' => true]);
