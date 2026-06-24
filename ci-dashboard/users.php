<?php

require_once __DIR__ . '/db.php';

function users_load(): array
{
    return db()->query('SELECT username, password_hash, totp_secret, is_admin, created_at FROM users ORDER BY id')
        ->fetchAll();
}

function users_find(string $username): ?array
{
    $stmt = db()->prepare('SELECT username, password_hash, totp_secret, is_admin, created_at FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function users_add(string $username, string $passwordHash, string $totpSecret, bool $isAdmin = false): void
{
    if (users_find($username) !== null) {
        throw new RuntimeException("User '$username' already exists.");
    }
    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, totp_secret, is_admin, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$username, $passwordHash, $totpSecret, $isAdmin ? 1 : 0, date('c')]);
}

function users_remove(string $username): void
{
    $stmt = db()->prepare('DELETE FROM users WHERE username = ?');
    $stmt->execute([$username]);
}

function users_count_admins(): int
{
    return (int)db()->query('SELECT COUNT(*) AS c FROM users WHERE is_admin = 1')->fetch()['c'];
}
