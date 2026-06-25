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

// Most recent workflow runs restricted to a single branch.
function github_list_runs_for_branch(string $token, string $owner, string $repo, string $branch, int $perPage = 1): array
{
    $qs = http_build_query(['branch' => $branch, 'per_page' => $perPage]);
    $data = github_request($token, 'GET', "/repos/$owner/$repo/actions/runs?$qs");
    return $data['workflow_runs'] ?? [];
}

function github_list_workflows(string $token, string $owner, string $repo): array
{
    $data = github_request($token, 'GET', "/repos/$owner/$repo/actions/workflows");
    return $data['workflows'] ?? [];
}

function github_get_repo(string $token, string $owner, string $repo): array
{
    return github_request($token, 'GET', "/repos/$owner/$repo");
}

// Repos the token's user can see, for the "add repo" dropdown. Note: this
// reflects what the user account can see, not strictly the fine-grained
// token's repo selection — GitHub API calls below will still fail per-repo
// if the token wasn't actually scoped to it.
function github_list_accessible_repos(string $token): array
{
    $repos = [];
    for ($page = 1; $page <= 3; $page++) {
        $qs = http_build_query(['per_page' => 100, 'page' => $page, 'sort' => 'pushed', 'affiliation' => 'owner,collaborator,organization_member']);
        $data = github_request($token, 'GET', "/user/repos?$qs");
        if (empty($data)) {
            break;
        }
        foreach ($data as $r) {
            $repos[] = $r['full_name'] ?? null;
        }
        if (count($data) < 100) {
            break;
        }
    }
    return array_values(array_filter($repos));
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
