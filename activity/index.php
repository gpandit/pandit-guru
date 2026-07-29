<?php
// gitboard public page (pandit.guru/activity/) — no token, safe subset only: names + recency + README blurbs.
// Visibility/naming managed from dashboard.php (data/hidden.json, data/names.json).
if (is_file(__DIR__ . '/config.php')) require __DIR__ . '/config.php';   // may define DATA_DIR
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');
// visibility controlled from the private board (data/hidden.json); fallback defaults
$hiddenFile = DATA_DIR . '/hidden.json';
$EXCLUDE = array_map('strtolower', is_file($hiddenFile)
    ? (json_decode(file_get_contents($hiddenFile), true) ?: [])
    : ['resume', 'HelloWorld', 'gitboard']);
$namesFile = DATA_DIR . '/names.json';
$NAMES = array_change_key_case(
    is_file($namesFile) ? (json_decode(file_get_contents($namesFile), true) ?: []) : [], CASE_LOWER);

$merged = [];   // keyed case-insensitively: dietassure == DietAssure
foreach (glob(DATA_DIR . '/*.json') ?: [] as $f) {
    $d = json_decode(file_get_contents($f), true);
    if (!$d || !isset($d['repos'])) continue;
    foreach ($d['repos'] as $r) {
        $k = strtolower($r['name']);
        if (in_array($k, $EXCLUDE)) continue;
        if (!isset($merged[$k]) || $r['last_commit_at'] > $merged[$k]['ts']) {
            $merged[$k] = ['ts' => $r['last_commit_at'], 'active' => $r['active'],
                           'orch' => !empty($r['orchestrator']),
                           'name' => $r['name'],
                           'desc' => $r['description'] ?? null];
        }
    }
}
uasort($merged, fn($a, $b) => $b['ts'] <=> $a['ts']);

function ago(int $ts): string {
    if (!$ts) return '—';
    $s = time() - $ts;
    foreach ([[86400*365,'year'],[86400*30,'month'],[86400,'day'],[3600,'hour'],[60,'minute']] as [$u,$l])
        if ($s >= $u) { $n = intdiv($s,$u); return "$n $l" . ($n > 1 ? 's' : '') . " ago"; }
    return 'moments ago';
}
function status(array $m): array {           // [label, css-class]
    $age = time() - $m['ts'];
    if ($m['active'])        return ['● building now', 'live'];
    if ($age < 86400*7)      return ['active this week', 'act'];
    if ($age < 86400*30)     return ['in progress', 'prog'];
    return ['paused', 'idle'];
}
function initials(string $n): string {
    preg_match_all('/[A-Z0-9]/', ucfirst($n), $m);
    return substr(implode('', $m[0]) ?: strtoupper($n), 0, 2);
}
function hue(string $n): int { return crc32($n) % 360; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>(function(){var t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="refresh" content="300">
<title>Build Activity — Guru Pandit</title>
<meta name="description" content="Live view of what Guru Pandit is building right now — updated automatically from the repos themselves." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://pandit.guru/styles.css">
<style>
 /* gitboard cards, styled on pandit.guru design tokens (gb- prefix avoids collisions) */
 .gb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-top:1.6rem}
 .gb-card{background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius-md);
          padding:1.1rem;display:flex;flex-direction:column;gap:.6rem}
 .gb-top{display:flex;align-items:center;gap:.7rem}
 .gb-avatar{width:42px;height:42px;border-radius:var(--radius-sm);display:grid;place-items:center;
            color:#fff;font-weight:700;font-size:.95rem;flex:none}
 .gb-name{font-weight:600;font-size:1.02rem;color:var(--fg);overflow-wrap:anywhere}
 .gb-badge{align-self:flex-start;font-size:.72rem;font-weight:600;padding:.15rem .6rem;border-radius:99px}
 .gb-live{background:rgba(34,197,94,.15);color:#4ade80;animation:gb-pulse 1.6s infinite}
 [data-theme="light"] .gb-live{color:#15803d}
 @keyframes gb-pulse{50%{opacity:.5}}
 .gb-act{background:rgba(34,197,94,.15);color:#4ade80}
 [data-theme="light"] .gb-act{color:#15803d}
 .gb-prog{background:rgba(245,158,11,.15);color:#fbbf24}
 [data-theme="light"] .gb-prog{color:#b45309}
 .gb-idle{background:var(--surface);border:1px solid var(--surface-border);color:var(--fg-muted)}
 .gb-desc{margin:0;color:var(--fg-muted);font-size:.85rem;line-height:1.45;display:-webkit-box;
          -webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
 .gb-when{color:var(--fg-muted);font-size:.8rem;margin-top:auto}
 .gb-tag{font-size:.7rem;color:var(--tag-fg);background:var(--tag-bg);border:1px solid var(--tag-border);
         padding:.12rem .5rem;border-radius:99px;align-self:flex-start}
 .gb-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap}
</style>
</head>
<body>

<header class="nav" id="nav">
  <div class="container nav-inner">
    <a href="https://pandit.guru/" class="logo">GURU<span>PANDIT</span></a>
    <nav class="nav-links" aria-label="Primary">
      <a href="https://pandit.guru/index.html#achievements">Achievements</a>
      <a href="https://pandit.guru/index.html#experience">Experience</a>
      <a href="https://pandit.guru/index.html#skills">Skills</a>
      <a href="https://pandit.guru/projects.html">Projects</a>
      <a href="https://pandit.guru/news-blog">News &amp; Blog</a>
      <a href="https://pandit.guru/index.html#contact">Contact</a>
    </nav>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle light/dark theme">
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    </button>
    <a class="btn btn-ghost nav-cta" href="mailto:hello@pandit.guru">Get in touch</a>
    <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <a href="https://pandit.guru/index.html#achievements">Achievements</a>
    <a href="https://pandit.guru/index.html#experience">Experience</a>
    <a href="https://pandit.guru/index.html#skills">Skills</a>
    <a href="https://pandit.guru/projects.html">Projects</a>
    <a href="https://pandit.guru/news-blog">News &amp; Blog</a>
    <a href="https://pandit.guru/index.html#contact">Contact</a>
    <a class="btn btn-primary" href="mailto:hello@pandit.guru">Get in touch</a>
  </div>
</header>

<main class="section">
 <div class="container">
  <div class="gb-head">
   <div>
    <div class="section-eyebrow">On the workbench</div>
    <h1 class="section-title">Build activity</h1>
   </div>
   <a class="btn btn-ghost" href="dashboard.php">Administer</a>
  </div>
  <p class="muted">Live view of what's being built right now — updated automatically from the repos themselves, every 10 minutes.</p>
  <div class="gb-grid">
<?php foreach ($merged as $key => $m): [$label, $cls] = status($m);
      $disp = $NAMES[$key] ?? $m['name']; ?>
   <div class="gb-card">
    <div class="gb-top">
     <div class="gb-avatar" style="background:hsl(<?= hue($key) ?>,55%,48%)"><?= h(initials($disp)) ?></div>
     <div class="gb-name"><?= h($disp) ?></div>
    </div>
    <span class="gb-badge gb-<?= $cls ?>"><?= $label ?></span>
    <?php if ($m['orch']): ?><span class="gb-tag">🤖 automated builds</span><?php endif; ?>
    <?php if ($m['desc']): ?><p class="gb-desc"><?= h($m['desc']) ?></p><?php endif; ?>
    <div class="gb-when">last progress <?= ago($m['ts']) ?></div>
   </div>
<?php endforeach; ?>
  </div>
 </div>
</main>

<footer class="footer">
  <div class="container footer-inner">
    <span>© <span id="year"></span> Guru Pandit</span>
    <span class="muted">IT Program &amp; Delivery Leadership · Tech Strategy</span>
    <div class="footer-socials">
      <a href="https://x.com/gurupandit" target="_blank" rel="noopener" aria-label="X (Twitter)">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2.5h3.3l-7.2 8.2 8.5 10.8h-6.6l-5.2-6.6-5.9 6.6H2.5l7.7-8.8L2 2.5h6.8l4.7 6.1 5.4-6.1zm-2.3 17.2h1.8L7.5 4.2H5.6l11 15.5z"/></svg>
      </a>
      <a href="https://instagram.com/gurupandit" target="_blank" rel="noopener" aria-label="Instagram">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 2.01.24 2.71.51.73.28 1.28.66 1.84 1.22.56.56.94 1.11 1.22 1.84.27.7.46 1.54.51 2.71.06 1.25.07 1.65.07 4.85s0 3.6-.07 4.85c-.05 1.17-.24 2.01-.51 2.71-.28.73-.66 1.28-1.22 1.84-.56.56-1.11.94-1.84 1.22-.7.27-1.54.46-2.71.51-1.25.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.17-.05-2.01-.24-2.71-.51a4.92 4.92 0 0 1-1.84-1.22 4.92 4.92 0 0 1-1.22-1.84c-.27-.7-.46-1.54-.51-2.71C2.21 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.05-1.17.24-2.01.51-2.71.28-.73.66-1.28 1.22-1.84A4.92 4.92 0 0 1 5.84 1.38c.7-.27 1.54-.46 2.71-.51C9.8 1.21 10.2 1.2 12 1.2zm0 1.98c-3.14 0-3.52 0-4.76.06-1.05.05-1.61.23-1.99.38-.5.19-.86.43-1.24.81-.38.38-.62.74-.81 1.24-.15.38-.33.94-.38 1.99-.06 1.24-.06 1.62-.06 4.76s0 3.52.06 4.76c.05 1.05.23 1.61.38 1.99.19.5.43.86.81 1.24.38.38.74.62 1.24.81.38.15.94.33 1.99.38 1.24.06 1.62.06 4.76.06s3.52 0 4.76-.06c1.05-.05 1.61-.23 1.99-.38.5-.19.86-.43 1.24-.81.38-.38.62-.74.81-1.24.15-.38.33-.94.38-1.99.06-1.24.06-1.62.06-4.76s0-3.52-.06-4.76c-.05-1.05-.23-1.61-.38-1.99a3.05 3.05 0 0 0-.81-1.24 3.05 3.05 0 0 0-1.24-.81c-.38-.15-.94-.33-1.99-.38-1.24-.06-1.62-.06-4.76-.06zm0 3.37a5.45 5.45 0 1 1 0 10.9 5.45 5.45 0 0 1 0-10.9zm0 1.98a3.47 3.47 0 1 0 0 6.94 3.47 3.47 0 0 0 0-6.94zm6.94-2.2a1.31 1.31 0 1 1-2.62 0 1.31 1.31 0 0 1 2.62 0z"/></svg>
      </a>
      <a href="https://linkedin.com/in/gurupandit" target="_blank" rel="noopener" aria-label="LinkedIn">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.03-1.85-3.03-1.85 0-2.14 1.45-2.14 2.94v5.66H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.38-1.85 3.61 0 4.28 2.38 4.28 5.47v6.27zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45z"/></svg>
      </a>
    </div>
  </div>
</footer>

<script src="https://pandit.guru/script.js"></script>
</body></html>
