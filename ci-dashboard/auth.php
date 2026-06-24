<?php

require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/users.php';

const AUTH_PENDING_TTL = 300; // seconds allowed between password step and TOTP step

function auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_is_logged_in(): bool
{
    return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function auth_current_user(): ?array
{
    if (!auth_is_logged_in() || empty($_SESSION['username'])) {
        return null;
    }
    return users_find($_SESSION['username']);
}

function auth_is_admin(): bool
{
    $u = auth_current_user();
    return $u !== null && !empty($u['is_admin']);
}

function auth_require_login(): void
{
    if (!auth_is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function auth_require_admin(): void
{
    auth_require_login();
    if (!auth_is_admin()) {
        http_response_code(403);
        echo 'Forbidden — admin access only.';
        exit;
    }
}

function auth_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function auth_csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

// Simple in-session rate limiting to slow down brute-force / TOTP-guessing attempts.
function auth_too_many_attempts(): bool
{
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;
    if ($attempts >= 5 && (time() - $lastAttempt) < 60) {
        return true;
    }
    if ((time() - $lastAttempt) > 60) {
        $_SESSION['login_attempts'] = 0;
    }
    return false;
}

function auth_record_attempt(bool $success): void
{
    if ($success) {
        $_SESSION['login_attempts'] = 0;
        return;
    }
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

// Step 1: verify username + password. On success, stash the username as
// "pending" so step 2 can ask for the TOTP code without re-checking the
// password. Does not log the user in yet.
function auth_attempt_password(string $username, string $password): bool
{
    $user = users_find($username);
    $ok = $user !== null && password_verify($password, $user['password_hash']);
    auth_record_attempt($ok);
    if ($ok) {
        $_SESSION['pending_username'] = $user['username'];
        $_SESSION['pending_at'] = time();
    }
    return $ok;
}

function auth_has_pending_login(): bool
{
    return !empty($_SESSION['pending_username'])
        && !empty($_SESSION['pending_at'])
        && (time() - $_SESSION['pending_at']) <= AUTH_PENDING_TTL;
}

function auth_pending_username(): ?string
{
    return auth_has_pending_login() ? $_SESSION['pending_username'] : null;
}

function auth_clear_pending(): void
{
    unset($_SESSION['pending_username'], $_SESSION['pending_at']);
}

// Step 2: verify the TOTP code for the pending user. On success, completes login.
function auth_attempt_totp(string $totpCode): bool
{
    $username = auth_pending_username();
    if ($username === null) {
        return false;
    }
    $user = users_find($username);
    $ok = $user !== null && totp_verify($user['totp_secret'], $totpCode);
    auth_record_attempt($ok);
    if ($ok) {
        auth_clear_pending();
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $ok;
}
