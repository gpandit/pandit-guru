<?php
// Copy this file to config.php and fill in real values.
// config.php is git-ignored and denied by .htaccess — never commit real secrets.
//
// User accounts (username/password/TOTP secret) live in the `users` MySQL
// table — created and seeded by migrate.php, managed afterwards via
// admin.php. They are not configured here.

return [
    // GitHub Personal Access Token. Needs:
    //   - "Actions" repository permission: Read and write
    //     (read to list runs, write to trigger workflow_dispatch)
    // Fine-grained PAT scoped to only the repos you list below is recommended.
    'github_token' => 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',

    // Issuer name shown in authenticator apps for all users.
    'totp_issuer' => 'PanditGuruCI',

    // How long cached GitHub API responses are reused, in seconds.
    'cache_ttl_seconds' => 60,

    // Used once by migrate.php to seed the first admin user, then ignored —
    // delete these two lines (or just leave them, they're inert) once the
    // `users` table already has rows. Pick your own password here; nothing
    // in this file should ever be committed to git.
    'seed_admin_username' => 'admin',
    'seed_admin_password' => 'change-me-before-running-migrate',

    // MySQL connection — use the DB credentials from Hostinger's hPanel
    // (Databases > MySQL Databases). Create an empty database there first.
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'uXXXXXXX_ci',
        'user' => 'uXXXXXXX_ci',
        'pass' => 'replace-with-real-password',

        // For local dev only: uncomment to use SQLite instead of MySQL.
        // 'dsn' => 'sqlite:' . __DIR__ . '/dev.sqlite',
    ],
];
