<?php
// Public, minimal companion to index.php: {repo-name-lowercase: last_commit_unix_ts}
// Used by projects.html to sort "GitHub build work" cards by recency client-side.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

if (is_file(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');

$hiddenFile = DATA_DIR . '/hidden.json';
$EXCLUDE = array_map('strtolower', is_file($hiddenFile)
    ? (json_decode(file_get_contents($hiddenFile), true) ?: [])
    : ['resume', 'HelloWorld', 'gitboard']);

$merged = [];
foreach (glob(DATA_DIR . '/*.json') ?: [] as $f) {
    $d = json_decode(file_get_contents($f), true);
    if (!$d || !isset($d['repos'])) continue;
    foreach ($d['repos'] as $r) {
        $k = strtolower($r['name']);
        if (in_array($k, $EXCLUDE)) continue;
        if (!isset($merged[$k]) || $r['last_commit_at'] > $merged[$k]) {
            $merged[$k] = (int) $r['last_commit_at'];
        }
    }
}

echo json_encode($merged);
