<?php

define('CACHE_DIR', __DIR__ . '/cache');

function cache_get(string $key, int $ttlSeconds): ?array
{
    $file = CACHE_DIR . '/' . sha1($key) . '.json';
    if (!file_exists($file)) {
        return null;
    }
    if (time() - filemtime($file) > $ttlSeconds) {
        return null;
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function cache_set(string $key, array $value): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0700, true);
    }
    $file = CACHE_DIR . '/' . sha1($key) . '.json';
    file_put_contents($file, json_encode($value));
}
