<?php

class GitHubApiException extends RuntimeException {}

function github_request(string $token, string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.github.com' . $path);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: pandit-guru-ci-dashboard',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new GitHubApiException('cURL error: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $raw !== '' ? json_decode($raw, true) : [];
    if ($status >= 300) {
        $msg = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : ('HTTP ' . $status);
        throw new GitHubApiException($msg, $status);
    }
    return is_array($decoded) ? $decoded : [];
}

// Returns the most recent workflow runs for a repo (default branch + all branches).
function github_list_runs(string $token, string $owner, string $repo, int $perPage = 5): array
{
    $data = github_request($token, 'GET', "/repos/$owner/$repo/actions/runs?per_page=$perPage");
    return $data['workflow_runs'] ?? [];
}

function github_list_workflows(string $token, string $owner, string $repo): array
{
    $data = github_request($token, 'GET', "/repos/$owner/$repo/actions/workflows");
    return $data['workflows'] ?? [];
}

// Triggers a workflow_dispatch event. $ref is the branch/tag to run on.
function github_dispatch_workflow(string $token, string $owner, string $repo, $workflowIdOrFile, string $ref): void
{
    github_request(
        $token,
        'POST',
        "/repos/$owner/$repo/actions/workflows/$workflowIdOrFile/dispatches",
        ['ref' => $ref]
    );
}
