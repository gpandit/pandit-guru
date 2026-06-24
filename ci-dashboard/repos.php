<?php

define('REPOS_FILE', __DIR__ . '/repos.json');

function repos_load(): array
{
    if (!file_exists(REPOS_FILE)) {
        return [];
    }
    $raw = file_get_contents(REPOS_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function repos_save(array $repos): void
{
    file_put_contents(REPOS_FILE, json_encode(array_values($repos), JSON_PRETTY_PRINT));
}

function repos_add(string $owner, string $repo): void
{
    $repos = repos_load();
    $key = strtolower("$owner/$repo");
    foreach ($repos as $r) {
        if (strtolower($r['owner'] . '/' . $r['repo']) === $key) {
            return; // already tracked
        }
    }
    $repos[] = ['owner' => $owner, 'repo' => $repo];
    repos_save($repos);
}

function repos_remove(string $owner, string $repo): void
{
    $repos = repos_load();
    $key = strtolower("$owner/$repo");
    $repos = array_filter($repos, fn($r) => strtolower($r['owner'] . '/' . $r['repo']) !== $key);
    repos_save($repos);
}
