<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/github.php';
require_once __DIR__ . '/repos.php';
require_once __DIR__ . '/cache.php';

auth_start_session();
// Viewing the dashboard is public; only the state-changing actions below
// (add/remove repo, trigger deploy) require a logged-in session.
$loggedIn = auth_is_logged_in();

$config = require __DIR__ . '/config.php';
$token = $config['github_token'];
$ttl = $config['cache_ttl_seconds'] ?? 60;

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$loggedIn) {
        header('Location: login.php');
        exit;
    }
    if (!auth_csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['error', 'Invalid form submission, please retry.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_repo') {
                $ownerRepo = trim($_POST['owner_repo'] ?? '');
                if (!preg_match('#^([\w.-]+)/([\w.-]+)$#', $ownerRepo, $m)) {
                    throw new RuntimeException('Enter repo as owner/repo, e.g. octocat/hello-world');
                }
                repos_add($m[1], $m[2]);
                $flash = ['ok', "Added {$m[1]}/{$m[2]}"];
            } elseif ($action === 'remove_repo') {
                $owner = $_POST['owner'] ?? '';
                $repo = $_POST['repo'] ?? '';
                repos_remove($owner, $repo);
                $flash = ['ok', "Removed $owner/$repo"];
            } elseif ($action === 'deploy') {
                $owner = $_POST['owner'] ?? '';
                $repo = $_POST['repo'] ?? '';
                $workflowId = $_POST['workflow_id'] ?? '';
                $ref = trim($_POST['ref'] ?? 'main');
                if ($owner === '' || $repo === '' || $workflowId === '') {
                    throw new RuntimeException('Missing repo or workflow for deploy trigger.');
                }
                github_dispatch_workflow($token, $owner, $repo, $workflowId, $ref);
                $flash = ['ok', "Triggered workflow on $owner/$repo@$ref"];
                // Drop the cached run for this branch so the card picks up the new run sooner.
                @unlink(CACHE_DIR . '/' . sha1("runs:$owner/$repo:$ref") . '.json');
            }
        } catch (Throwable $e) {
            $flash = ['error', $e->getMessage()];
        }
    }
    header('Location: index.php');
    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
    exit;
}

if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$repos = repos_load();
$cards = [];

foreach ($repos as $r) {
    $owner = $r['owner'];
    $repo = $r['repo'];
    $fetchError = null;
    $branchRows = [];

    try {
        $repoInfoKey = "repoinfo:$owner/$repo";
        $repoInfo = cache_get($repoInfoKey, 600);
        if ($repoInfo === null) {
            $repoInfo = github_get_repo($token, $owner, $repo);
            cache_set($repoInfoKey, $repoInfo);
        }
        $defaultBranch = $repoInfo['default_branch'] ?? 'main';

        $branchesToShow = [$defaultBranch];
        if ($defaultBranch !== 'staging') {
            $stagingKey = "branchexists:$owner/$repo:staging";
            $stagingExists = cache_get($stagingKey, 600);
            if ($stagingExists === null) {
                $stagingExists = ['exists' => github_branch_exists($token, $owner, $repo, 'staging')];
                cache_set($stagingKey, $stagingExists);
            }
            if ($stagingExists['exists']) {
                $branchesToShow[] = 'staging';
            }
        }

        foreach ($branchesToShow as $branch) {
            $cacheKey = "runs:$owner/$repo:$branch";
            $runs = cache_get($cacheKey, $ttl);
            if ($runs === null) {
                $runs = github_list_runs_for_branch($token, $owner, $repo, $branch, 1);
                cache_set($cacheKey, $runs);
            }
            $branchRows[] = ['branch' => $branch, 'run' => $runs[0] ?? null];
        }
    } catch (Throwable $e) {
        $fetchError = $e->getMessage();
    }

    $workflows = [];
    if ($loggedIn) {
        // Only needed to populate the deploy-trigger control, which
        // anonymous viewers don't see — skip the extra API call for them.
        $wfCacheKey = "workflows:$owner/$repo";
        $workflows = cache_get($wfCacheKey, 600);
        if ($workflows === null) {
            try {
                $workflows = github_list_workflows($token, $owner, $repo);
                cache_set($wfCacheKey, $workflows);
            } catch (Throwable $e) {
                $workflows = [];
            }
        }
    }

    $cards[] = [
        'owner' => $owner,
        'repo' => $repo,
        'branchRows' => $branchRows,
        'fetchError' => $fetchError,
        'workflows' => $workflows,
    ];
}

$accessibleRepos = [];
if ($loggedIn) {
    $accCacheKey = 'accessible_repos';
    $accessibleRepos = cache_get($accCacheKey, 600);
    if ($accessibleRepos === null) {
        try {
            $accessibleRepos = github_list_accessible_repos($token);
            cache_set($accCacheKey, $accessibleRepos);
        } catch (Throwable $e) {
            $accessibleRepos = [];
        }
    }
    $trackedKeys = array_map(fn($r) => strtolower($r['owner'] . '/' . $r['repo']), $repos);
    $accessibleRepos = array_values(array_filter($accessibleRepos, fn($full) => !in_array(strtolower($full), $trackedKeys, true)));
    sort($accessibleRepos);
}

function ci_initials(string $repo): string
{
    $parts = preg_split('/[-_.\s]+/', $repo, -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $initials !== '' ? $initials : mb_strtoupper(mb_substr($repo, 0, 2));
}

// Maps a run's status/conclusion onto the site's existing badge classes
// (status-active/status-progress/status-delivered from styles.css), adding
// one new status-failure variant for failed runs. Headline reflects the
// default branch's latest run (first row), not PR/feature-branch builds.
function ci_headline_badge(array $branchRows): array
{
    $run = $branchRows[0]['run'] ?? null;
    if ($run === null) {
        return ['status-delivered', 'No runs yet'];
    }
    $status = $run['status'] ?? '';
    $conclusion = $run['conclusion'] ?? null;

    if ($status !== 'completed') {
        return ['status-progress', $status === 'queued' ? 'Queued' : 'Running'];
    }
    return match ($conclusion) {
        'success' => ['status-active', 'Passing'],
        'failure', 'timed_out' => ['status-failure', 'Failing'],
        'cancelled' => ['status-delivered', 'Cancelled'],
        default => ['status-delivered', $conclusion ?? 'Unknown'],
    };
}

function ci_run_badge_class(?string $conclusion, string $status): string
{
    if ($status !== 'completed') {
        return 'run-badge-progress';
    }
    return match ($conclusion) {
        'success' => 'run-badge-success',
        'failure', 'timed_out' => 'run-badge-failure',
        'cancelled' => 'run-badge-cancelled',
        default => 'run-badge-neutral',
    };
}

function ci_relative_time(?string $iso): string
{
    if (!$iso) {
        return '-';
    }
    $diff = time() - strtotime($iso);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

$csrf = $loggedIn ? auth_csrf_token() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>(function(){var t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CI/CD Dashboard — Guru Pandit</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<style>
  /* Dashboard-specific extras layered on top of the main site's tokens/cards. */
  .ci-card { padding: 0; }
  .ci-card .project-thumb { height: 92px; font-size: 22px; }
  .ci-card .project-body { padding: 22px 24px; gap: 10px; }
  .ci-card h3 { word-break: break-word; }
  .project-status.status-failure {
    background: rgba(239,68,68,0.12);
    color: #ef4444;
    border-color: rgba(239,68,68,0.3);
  }
  .ci-runs { display: flex; flex-direction: column; gap: 6px; }
  .ci-run-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--fg-muted);
    flex-wrap: wrap;
  }
  .ci-run-row a { color: var(--accent-2); }
  .run-badge {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid transparent;
  }
  .run-badge-success { background: rgba(34,197,94,0.12); color: #22c55e; border-color: rgba(34,197,94,0.3); }
  .run-badge-failure { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.3); }
  .run-badge-progress { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.3); }
  .run-badge-cancelled, .run-badge-neutral { background: var(--tag-bg); color: var(--tag-fg); border-color: var(--tag-border); }
  .ci-card-error { color: #ef4444; font-size: 13px; }
  .ci-manage { border-top: 1px solid var(--surface-border); padding-top: 14px; display: flex; flex-direction: column; gap: 10px; }
  .ci-manage form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  .ci-manage select, .ci-manage input[type=text] {
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--surface-border);
    background: var(--bg-elev);
    color: var(--fg);
    font-size: 13px;
  }
  .ci-manage input[type=text] { width: 90px; }
  .btn-sm { padding: 6px 12px; font-size: 13px; border-radius: var(--radius-sm); }
  .btn-danger { background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
  .btn-danger:hover { background: rgba(239,68,68,0.2); }
  .add-repo-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 32px 24px;
    text-align: center;
  }
  .add-repo-card form { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
  .flash { padding: 12px 18px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 14px; }
  .flash-ok { background: rgba(34,197,94,0.12); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
  .flash-error { background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
  .ci-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }
  @media (max-width: 900px) { .ci-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="bg-glow" aria-hidden="true"></div>

<header class="nav" id="nav">
  <div class="container nav-inner">
    <a href="../index.html" class="logo">GURU<span>PANDIT</span></a>
    <nav class="nav-links" aria-label="Primary">
      <a href="../projects.html">Projects</a>
      <a href="index.php" aria-current="page">CI/CD</a>
    </nav>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle light/dark theme">
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    </button>
    <a class="btn btn-ghost nav-cta" href="../projects.html">&laquo; Back to Projects</a>
    <?php if ($loggedIn): ?>
      <a class="btn btn-ghost nav-cta" href="admin.php">Admin</a>
      <a class="btn btn-primary nav-cta" href="logout.php">Log out</a>
    <?php else: ?>
      <a class="btn btn-primary nav-cta" href="login.php">Log in</a>
    <?php endif; ?>
  </div>
</header>

<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow reveal">Build status</p>
      <h1 class="reveal">CI/CD Dashboard</h1>
      <p class="reveal">Live GitHub Actions status across tracked repos.<?= $loggedIn ? '' : ' Log in to add/remove repos or trigger a deploy.' ?></p>
    </div>
  </section>

  <section class="section">
    <div class="container">

      <?php if ($flash): [$kind, $msg] = $flash; ?>
        <div class="flash flash-<?= $kind === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="ci-grid reveal">
        <?php foreach ($cards as $card): ?>
          <?php
            $key = $card['owner'] . '/' . $card['repo'];
            [$badgeClass, $badgeLabel] = ci_headline_badge($card['branchRows']);
          ?>
          <div class="project-card ci-card glass">
            <div class="project-thumb">
              <span class="thumb-fallback"><?= htmlspecialchars(ci_initials($card['repo'])) ?></span>
            </div>
            <div class="project-body">
              <span class="project-status <?= $badgeClass ?>"><?= htmlspecialchars($badgeLabel) ?></span>
              <h3><?= htmlspecialchars($key) ?></h3>

              <?php if ($card['fetchError']): ?>
                <p class="ci-card-error"><?= htmlspecialchars($card['fetchError']) ?></p>
              <?php elseif (empty($card['branchRows'])): ?>
                <p class="muted" style="font-size:13px;">No workflow runs found yet.</p>
              <?php else: ?>
                <div class="ci-runs">
                  <?php foreach ($card['branchRows'] as $row): $run = $row['run']; ?>
                    <div class="ci-run-row">
                      <strong><?= htmlspecialchars($row['branch']) ?></strong>
                      <?php if ($run === null): ?>
                        <span class="muted">no runs</span>
                      <?php else: ?>
                        <span class="run-badge <?= ci_run_badge_class($run['conclusion'] ?? null, $run['status'] ?? '') ?>">
                          <?= htmlspecialchars($run['conclusion'] ?? $run['status'] ?? '-') ?>
                        </span>
                        <?php if (!empty($run['html_url'])): ?>
                          <a href="<?= htmlspecialchars($run['html_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(substr($run['head_sha'] ?? '-', 0, 7)) ?></a>
                        <?php else: ?>
                          <span><?= htmlspecialchars(substr($run['head_sha'] ?? '-', 0, 7)) ?></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars(ci_relative_time($run['updated_at'] ?? null)) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($loggedIn): ?>
                <div class="ci-manage">
                  <?php if (!empty($card['workflows'])): ?>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="deploy">
                      <input type="hidden" name="owner" value="<?= htmlspecialchars($card['owner']) ?>">
                      <input type="hidden" name="repo" value="<?= htmlspecialchars($card['repo']) ?>">
                      <select name="workflow_id">
                        <?php foreach ($card['workflows'] as $wf): ?>
                          <option value="<?= htmlspecialchars($wf['id']) ?>"><?= htmlspecialchars($wf['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="text" name="ref" value="main" placeholder="branch">
                      <button type="submit" class="btn btn-primary btn-sm">Run workflow</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Remove this repo from the dashboard?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="remove_repo">
                    <input type="hidden" name="owner" value="<?= htmlspecialchars($card['owner']) ?>">
                    <input type="hidden" name="repo" value="<?= htmlspecialchars($card['repo']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Remove repo</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if ($loggedIn): ?>
          <div class="project-card glass add-repo-card">
            <p class="muted" style="margin:0;">Track a new repo</p>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="add_repo">
              <?php if (!empty($accessibleRepos)): ?>
                <select name="owner_repo" required>
                  <option value="" disabled selected>Select a repo&hellip;</option>
                  <?php foreach ($accessibleRepos as $full): ?>
                    <option value="<?= htmlspecialchars($full) ?>"><?= htmlspecialchars($full) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" name="owner_repo" placeholder="owner/repo" required>
                <p class="muted" style="font-size:12px;margin:0;">Couldn't list repos from GitHub (check token permissions) — type owner/repo manually.</p>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary btn-sm">Add repo</button>
            </form>
          </div>
        <?php endif; ?>

        <?php if (empty($cards) && !$loggedIn): ?>
          <p class="muted">No repos tracked yet.</p>
        <?php endif; ?>
      </div>

      <p class="muted" style="margin-top:24px; font-size:13px;">Cache TTL: <?= (int)$ttl ?>s</p>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container footer-inner">
    <span>© <span id="year"></span> Guru Pandit</span>
    <span class="muted">CI/CD Dashboard</span>
  </div>
</footer>

<script src="../script.js"></script>
</body>
</html>
