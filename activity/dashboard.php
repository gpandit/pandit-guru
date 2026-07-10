<?php
// gitboard private dashboard (token/cookie gated). Public page = index.php.
// POST ?token=…  with JSON body  → stores data/<device>.json  (collector.py upload)
// GET  ?token=… once per browser → sets cookie, then plain dashboard.php works
// Secrets live in activity/config.php — gitignored, uploaded once via File Manager:
//   <?php const TOKEN = '<long random string>';   // optionally: define('DATA_DIR', '/path');
if (is_file(__DIR__ . '/config.php')) require __DIR__ . '/config.php';
if (!defined('TOKEN')) { http_response_code(500); exit('activity/config.php missing — define TOKEN'); }
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');

$tok = $_GET['token'] ?? $_COOKIE['gitboard'] ?? '';
if ($tok !== TOKEN) {
    http_response_code(403);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') exit('forbidden');   // collector POST: plain reply
    exit('<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="2;url=./">'
       . '<body style="font-family:system-ui;display:grid;place-items:center;height:90vh">'
       . '<p>🔒 Not authorised — returning to the <a href="./">public page</a>…</p>');
}
// browser visited with ?token= → set year-long cookie, redirect to clean URL
if (isset($_GET['token']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    setcookie('gitboard', TOKEN, ['expires' => time() + 31536000, 'path' => '/',
                                  'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// public-page visibility toggle (from the board UI); state shared with public.php
const HIDDEN_FILE = DATA_DIR . '/hidden.json';
$hidden = array_map('strtolower', is_file(HIDDEN_FILE)
    ? (json_decode(file_get_contents(HIDDEN_FILE), true) ?: [])
    : ['resume', 'HelloWorld', 'gitboard']);
// public display names (e.g. dietassure → DietAssure™); empty = revert to repo name
const NAMES_FILE = DATA_DIR . '/names.json';
$names = array_change_key_case(
    is_file(NAMES_FILE) ? (json_decode(file_get_contents(NAMES_FILE), true) ?: []) : [], CASE_LOWER);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_repo'])) {
    $n = strtolower($_POST['rename_repo']);
    $v = trim($_POST['public_name'] ?? '');
    if ($v === '') unset($names[$n]); else $names[$n] = mb_substr($v, 0, 60);
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents(NAMES_FILE, json_encode($names, JSON_UNESCAPED_UNICODE));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_repo'])) {
    $n = strtolower($_POST['toggle_repo']);
    $hidden = in_array($n, $hidden) ? array_values(array_diff($hidden, [$n]))
                                    : array_merge($hidden, [$n]);
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents(HIDDEN_FILE, json_encode($hidden));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = json_decode(file_get_contents('php://input'), true);
    if (!$p || !preg_match('/^[\w.-]{1,64}$/', $p['device'] ?? '')) { http_response_code(400); exit('bad payload'); }
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents(DATA_DIR . '/' . $p['device'] . '.json', json_encode($p));
    exit('ok');
}

$devices = [];
foreach (glob(DATA_DIR . '/*.json') ?: [] as $f)
    if (($d = json_decode(file_get_contents($f), true)) && isset($d['repos'], $d['device'])) $devices[] = $d;
usort($devices, fn($a, $b) => strcmp($a['device'], $b['device']));

// merge: one row per repo, keyed case-insensitively (dietassure == DietAssure),
// keeping the copy with the newest commit + which device has it
$merged = [];
foreach ($devices as $d) {
    foreach ($d['repos'] as $r) {
        $k = strtolower($r['name']);
        $merged[$k]['devices'][$d['device']] = $r['last_commit_at'];
        if (!isset($merged[$k]['repo']) || $r['last_commit_at'] > $merged[$k]['repo']['last_commit_at']) {
            $merged[$k]['repo'] = $r;
            $merged[$k]['device'] = $d['device'];
        }
    }
}
uasort($merged, fn($a, $b) => $b['repo']['last_commit_at'] <=> $a['repo']['last_commit_at']);

function ago(int $ts): string {
    if (!$ts) return 'never';
    $s = time() - $ts;
    foreach ([[86400*30,'mo'],[86400,'d'],[3600,'h'],[60,'m']] as [$u,$l])
        if ($s >= $u) return intdiv($s,$u) . $l . ' ago';
    return 'just now';
}
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="120">
<title>gitboard — work in progress</title>
<style>
 :root{color-scheme:light dark;font-family:ui-sans-serif,system-ui,sans-serif}
 body{margin:0 auto;max-width:960px;padding:1rem;background:Canvas;color:CanvasText}
 h1{font-size:1.2rem} h2{font-size:.9rem;opacity:.7;margin:1.4rem 0 .4rem;text-transform:uppercase;letter-spacing:.05em}
 details{border:1px solid color-mix(in srgb,CanvasText 15%,transparent);border-radius:8px;margin:.4rem 0;overflow:hidden}
 summary{display:flex;gap:.7rem;align-items:baseline;flex-wrap:wrap;padding:.55rem .8rem;cursor:pointer;list-style:none}
 summary::-webkit-details-marker{display:none}
 .name{font-weight:600;min-width:9rem}
 .badge{font-size:.72rem;padding:.1rem .5rem;border-radius:99px;white-space:nowrap}
 .active{background:#16a34a;color:#fff;animation:pulse 1.5s infinite}
 @keyframes pulse{50%{opacity:.55}}
 .idle{background:color-mix(in srgb,CanvasText 12%,transparent)}
 .orch{background:#7c3aed;color:#fff}
 .dirty{background:#d97706;color:#fff}
 .dev{background:#2563eb;color:#fff}
 .date{margin-left:auto;font-size:.8rem;opacity:.65;white-space:nowrap}
 .work{font-size:.85rem;opacity:.85;flex-basis:100%}
 .body{padding:.6rem .9rem;border-top:1px solid color-mix(in srgb,CanvasText 12%,transparent);font-size:.85rem;line-height:1.55}
 .body table{border-collapse:collapse}.body td{padding:.1rem .8rem .1rem 0;vertical-align:top}
 .stale{opacity:.55}
 footer{margin-top:2rem;font-size:.75rem;opacity:.5}
</style></head><body>
<h1 style="display:flex;justify-content:space-between;align-items:baseline">🛰 gitboard
 <a href="./" style="font-size:.75rem;font-weight:400;opacity:.7">← public page</a></h1>
<?php if (!$devices): ?><p>No data yet — run <code>collector.py</code> on a device.</p><?php endif; ?>
<p>
<?php foreach ($devices as $d): $stale = time() - $d['generated_at'] > 3600; ?>
 <span class="badge <?= $stale ? 'idle stale' : 'dev' ?>"><?= h($d['device']) ?> · <?= ago($d['generated_at']) ?><?= $stale ? ' ⚠' : '' ?></span>
<?php endforeach; ?>
</p>
<?php foreach ($merged as $name => $m):
    $r = $m['repo'];
    $o = $r['orchestrator'];
    $orchActive = $o && $r['active'];
    $work = $r['last_commit'];
    if ($o && $o['in_progress']) $work = 'WP in progress: ' . implode(', ', $o['in_progress']);
?>
<details>
 <summary>
  <span class="name"><?= h($r['name']) ?></span>
  <?php if ($orchActive): ?><span class="badge active">● orchestrator working</span>
  <?php elseif ($r['active']): ?><span class="badge active">● active now</span>
  <?php elseif ($o): ?><span class="badge orch">orchestrated</span>
  <?php else: ?><span class="badge idle"><?= h($r['branch'] ?: 'detached') ?></span><?php endif; ?>
  <span class="badge dev">📍 <?= h($m['device']) ?></span>
  <?php if (in_array($name, $hidden)): ?><span class="badge idle">🙈 not public</span><?php endif; ?>
  <?php if ($r['dirty_files']): ?><span class="badge dirty"><?= $r['dirty_files'] ?> uncommitted</span><?php endif; ?>
  <span class="date">last progress <?= ago($r['last_commit_at']) ?></span>
  <span class="work"><?= h($work) ?></span>
 </summary>
 <div class="body"><table>
  <tr><td>Branch</td><td><?= h($r['branch']) ?><?= $r['unpushed'] ? " · {$r['unpushed']} unpushed" : '' ?></td></tr>
  <tr><td>Last commit</td><td><?= h($r['last_commit']) ?> · <?= $r['last_commit_at'] ? date('Y-m-d H:i', $r['last_commit_at']) : '—' ?></td></tr>
  <tr><td>Devices</td><td><?php
    arsort($m['devices']);
    echo h(implode(' · ', array_map(fn($dev, $ts) => "$dev (" . ago($ts) . ")",
                                    array_keys($m['devices']), $m['devices'])));
  ?></td></tr>
  <tr><td>Remote</td><td><?= $r['remote'] ? h($r['remote']) : '⚠ none — not backed up' ?></td></tr>
  <tr><td>Public page</td><td><?= in_array($name, $hidden) ? 'hidden' : 'shown' ?>
    <form method="post" style="display:inline;margin-left:.5rem">
     <input type="hidden" name="toggle_repo" value="<?= h($name) ?>">
     <button type="submit"><?= in_array($name, $hidden) ? 'show on public page' : 'hide from public page' ?></button>
    </form></td></tr>
  <tr><td>Public name</td><td>
    <form method="post" style="display:inline">
     <input type="hidden" name="rename_repo" value="<?= h($name) ?>">
     <input type="text" name="public_name" value="<?= h($names[$name] ?? '') ?>" placeholder="<?= h($r['name']) ?>" maxlength="60">
     <button type="submit">save</button>
    </form> <small>(blank = use repo name; paste ™ / ® as needed)</small></td></tr>
  <?php if ($o && $o['counts']): $c = $o['counts']; ?>
  <tr><td>Orchestrator</td><td><?= $c['done'] ?> done · <?= $c['in_progress'] ?> in progress · <?= $c['parked'] ?> parked · <?= $c['blocked'] ?> blocked · <?= $c['pending'] ?> pending</td></tr>
  <?php if ($o['waiting']): ?><tr><td>Waiting on you</td><td><?= h(implode(' · ', $o['waiting'])) ?></td></tr><?php endif; ?>
  <?php endif; ?>
 </table></div>
</details>
<?php endforeach; ?>
<footer>auto-refreshes every 2 min · green = git activity in the last 15 min · data pushed by collector.py cron on each device</footer>
</body></html>
