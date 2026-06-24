<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $config = require __DIR__ . '/config.php';
    $db = $config['db'] ?? [];

    if (!empty($db['dsn'])) {
        // Used for local/dev testing, e.g. 'sqlite:' . __DIR__ . '/dev.sqlite'
        $dsn = $db['dsn'];
        $user = $db['user'] ?? null;
        $pass = $db['pass'] ?? null;
    } else {
        // Standard Hostinger MySQL connection (host/name/user/pass from hPanel).
        $host = $db['host'] ?? 'localhost';
        $port = $db['port'] ?? 3306;
        $name = $db['name'] ?? '';
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $user = $db['user'] ?? '';
        $pass = $db['pass'] ?? '';
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}
