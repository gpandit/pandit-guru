<?php
// One-time (and safe to re-run) setup: creates the `users` table and seeds
// the initial admin account if the table is empty.
//
// Run via SSH:           php migrate.php
// Or once over HTTPS:    https://yourdomain/ci-dashboard/migrate.php
//                        (then delete this file, or leave the .htaccess
//                        deny rule in place — it's harmless to re-run but
//                        shouldn't stay reachable indefinitely)

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/totp.php';

$config = require __DIR__ . '/config.php';

$pdo = db();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            totp_secret VARCHAR(64) NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            totp_secret VARCHAR(64) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

header('Content-Type: text/plain');
echo "users table ready ($driver).\n\n";

$count = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
if ($count > 0) {
    echo "Users table already has $count user(s) — not seeding an admin.\n";
    echo "Use admin.php (while logged in as an existing admin) to add more.\n";
    exit;
}

$username = $config['seed_admin_username'] ?? 'admin';
$password = $config['seed_admin_password'] ?? null;
if (!$password) {
    echo "Set 'seed_admin_username' / 'seed_admin_password' in config.php before running this, then re-run.\n";
    exit(1);
}
$secret = totp_generate_secret();
$issuer = $config['totp_issuer'] ?? 'PanditGuruCI';
$uri = totp_otpauth_uri($secret, $username, $issuer);

$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, totp_secret, is_admin, created_at) VALUES (?, ?, ?, 1, ?)'
);
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $secret, date('c')]);

echo "Seeded admin user:\n";
echo "  username: $username\n";
echo "  password: $password  (change it via admin.php once you can — there's no self-service password change yet, ask to add one if needed)\n\n";
echo "=== TOTP secret (manual entry into your authenticator app) ===\n$secret\n\n";
echo "=== otpauth URI ===\n$uri\n\n";
echo "This admin can create further users from admin.php after logging in.\n";
echo "!! Delete or otherwise stop serving migrate.php now. !!\n";
