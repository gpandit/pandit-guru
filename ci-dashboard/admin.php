<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/totp.php';

auth_start_session();
auth_require_admin();

$config = require __DIR__ . '/config.php';
$issuer = $config['totp_issuer'] ?? 'PanditGuruCI';

$flash = null;
$newUserSetup = null; // holds secret/otpauth for a just-created user, shown once

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['error', 'Invalid form submission, please retry.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_user') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $isAdmin = !empty($_POST['is_admin']);
                if ($username === '' || !preg_match('/^[\w.-]{3,32}$/', $username)) {
                    throw new RuntimeException('Username must be 3-32 chars, letters/numbers/._- only.');
                }
                if (strlen($password) < 10) {
                    throw new RuntimeException('Password must be at least 10 characters.');
                }
                $secret = totp_generate_secret();
                users_add($username, password_hash($password, PASSWORD_DEFAULT), $secret, $isAdmin);
                $newUserSetup = [
                    'username' => $username,
                    'secret' => $secret,
                    'otpauth' => totp_otpauth_uri($secret, $username, $issuer),
                ];
                $flash = ['ok', "Created user '$username'. Have them scan/enter the code below into their authenticator app now — it won't be shown again."];
            } elseif ($action === 'remove_user') {
                $username = $_POST['username'] ?? '';
                if (strtolower($username) === strtolower($_SESSION['username'])) {
                    throw new RuntimeException('You cannot remove the account you are currently logged in as.');
                }
                $target = users_find($username);
                if ($target && !empty($target['is_admin']) && users_count_admins() <= 1) {
                    throw new RuntimeException('Cannot remove the last remaining admin.');
                }
                users_remove($username);
                $flash = ['ok', "Removed user '$username'."];
            }
        } catch (Throwable $e) {
            $flash = ['error', $e->getMessage()];
        }
    }
}

$users = users_load();
$csrf = auth_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CI/CD Dashboard — Admin</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 2rem; }
  h1 { font-size: 1.3rem; display: flex; justify-content: space-between; align-items: center; }
  a { color: #60a5fa; }
  table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
  th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid #1e293b; font-size: 0.85rem; }
  th { color: #94a3b8; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; }
  .panel { background: #1e293b; padding: 1rem 1.25rem; border-radius: 8px; margin-top: 1.5rem; }
  .panel h2 { font-size: 0.9rem; margin: 0 0 0.75rem; }
  label { display: block; margin-top: 0.6rem; font-size: 0.85rem; }
  input[type=text], input[type=password] { width: 100%; padding: 0.45rem; margin-top: 0.25rem; border-radius: 4px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; box-sizing: border-box; }
  button { margin-top: 1rem; padding: 0.5rem 1rem; border: none; border-radius: 4px; background: #2563eb; color: white; font-weight: 600; cursor: pointer; }
  button.danger { background: #b91c1c; padding: 0.3rem 0.7rem; margin-top: 0; }
  .flash { padding: 0.6rem 1rem; border-radius: 6px; margin-top: 1rem; font-size: 0.85rem; }
  .flash-ok { background: #14532d; color: #bbf7d0; }
  .flash-error { background: #7f1d1d; color: #fecaca; }
  .setup-box { background: #0f172a; border: 1px solid #334155; border-radius: 6px; padding: 1rem; margin-top: 1rem; font-size: 0.85rem; word-break: break-all; }
  code { color: #fbbf24; }
</style>
</head>
<body>

<h1>Admin <a href="index.php">&laquo; Back to dashboard</a></h1>

<?php if ($flash): [$kind, $msg] = $flash; ?>
  <div class="flash flash-<?= $kind === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($newUserSetup): ?>
  <div class="setup-box">
    <strong>One-time setup for <?= htmlspecialchars($newUserSetup['username']) ?>:</strong><br><br>
    TOTP secret (manual entry): <code><?= htmlspecialchars($newUserSetup['secret']) ?></code><br><br>
    otpauth URI: <code><?= htmlspecialchars($newUserSetup['otpauth']) ?></code><br><br>
    This is shown once and is not stored anywhere retrievable — copy it to the new user now.
  </div>
<?php endif; ?>

<div class="panel">
  <h2>Users</h2>
  <table>
    <thead><tr><th>Username</th><th>Admin</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= !empty($u['is_admin']) ? 'Yes' : 'No' ?></td>
        <td><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Remove this user?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="remove_user">
            <input type="hidden" name="username" value="<?= htmlspecialchars($u['username']) ?>">
            <button type="submit" class="danger">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>Add user</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="add_user">
    <label>Username<input type="text" name="username" required></label>
    <label>Temporary password (10+ chars, have them change it isn't supported yet — share over a secure channel)<input type="text" name="password" required></label>
    <label><input type="checkbox" name="is_admin" style="width:auto;display:inline;"> Grant admin (can manage users)</label>
    <button type="submit">Create user</button>
  </form>
</div>

</body>
</html>
