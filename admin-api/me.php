<?php
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/users.php';

if (!is_admin()) {
  json_out(['authed' => false, 'mfa_pending' => is_mfa_pending()]);
}

$u = current_user();
if (!$u) {
  reset_session();
  json_out(['authed' => false, 'mfa_pending' => false]);
}

json_out(['authed' => true, 'user' => user_public($u)]);
