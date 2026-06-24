<?php
require_once __DIR__ . '/auth.php';
auth_start_session();

if (auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['restart'])) {
    auth_clear_pending();
    header('Location: login.php');
    exit;
}

$error = null;
$step = auth_has_pending_login() ? 2 : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (auth_too_many_attempts()) {
        $error = 'Too many attempts. Wait a minute and try again.';
    } elseif (($_POST['step'] ?? '') === '1') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (auth_attempt_password($username, $password)) {
            $step = 2;
        } else {
            $error = 'Invalid username or password.';
            $step = 1;
        }
    } elseif (($_POST['step'] ?? '') === '2') {
        if (!auth_has_pending_login()) {
            $error = 'Session expired, please log in again.';
            $step = 1;
        } else {
            $totpCode = $_POST['totp_code'] ?? '';
            if (auth_attempt_totp($totpCode)) {
                header('Location: index.php');
                exit;
            }
            $error = 'Invalid authenticator code.';
            $step = 2;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Fresh GET: if there's a stale pending login from a previous attempt
    // that the user abandoned, let them restart from step 1 cleanly.
    $step = auth_has_pending_login() ? 2 : 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CI/CD Dashboard — Login</title>
<meta name="robots" content="noindex, nofollow">
<style>
  body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
  form { background: #1e293b; padding: 2rem; border-radius: 8px; width: 300px; }
  h1 { font-size: 1.1rem; margin: 0 0 1rem; }
  p.hint { font-size: 0.8rem; color: #94a3b8; margin: 0 0 0.5rem; }
  label { display: block; margin-top: 0.75rem; font-size: 0.85rem; }
  input { width: 100%; padding: 0.5rem; margin-top: 0.25rem; border-radius: 4px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; box-sizing: border-box; }
  button { margin-top: 1.25rem; width: 100%; padding: 0.6rem; border: none; border-radius: 4px; background: #2563eb; color: white; font-weight: 600; cursor: pointer; }
  .error { color: #f87171; font-size: 0.85rem; margin-top: 0.75rem; }
  a.back { display: block; margin-top: 0.75rem; font-size: 0.8rem; color: #60a5fa; }
</style>
</head>
<body>

<?php if ($step === 1): ?>
<form method="post" autocomplete="off">
  <input type="hidden" name="step" value="1">
  <h1>CI/CD Dashboard</h1>
  <label>Username<input type="text" name="username" required autofocus></label>
  <label>Password<input type="password" name="password" required></label>
  <button type="submit">Continue</button>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
</form>
<?php else: ?>
<form method="post" autocomplete="off">
  <input type="hidden" name="step" value="2">
  <h1>Enter authenticator code</h1>
  <p class="hint">Signed in as <strong><?= htmlspecialchars(auth_pending_username() ?? '') ?></strong>. Open your authenticator app and enter the current 6-digit code.</p>
  <label>Authenticator code<input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus></label>
  <button type="submit">Log in</button>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <a class="back" href="login.php?restart=1">&laquo; back to username/password</a>
</form>
<?php endif; ?>

</body>
</html>
