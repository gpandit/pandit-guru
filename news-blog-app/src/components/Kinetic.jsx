import { useRef, useEffect, useState } from 'react';
import {
  motion,
  useInView,
  useMotionValue,
  useSpring,
  useTransform,
  useScroll,
} from 'framer-motion';

/* ── Reveal: fade-up on scroll into view, with optional delay ── */
export function Reveal({ children, delay = 0, y = 28, className, ...rest }) {
  return (
    <motion.div
      className={className}
      initial={{ opacity: 0, y }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.15 }}
      transition={{ duration: 0.65, delay, ease: [0.21, 0.6, 0.35, 1] }}
      {...rest}
    >
      {children}
    </motion.div>
  );
}

/* ── Paragraphs: split copy on blank lines ("\n\n") into separate <p> tags ── */
export function Paragraphs({ text, className }) {
  return String(text).split(/\n\s*\n/).map((para, i) => (
    <p key={i} className={className}>{para.trim()}</p>
  ));
}

/* ── StaggerText: word-by-word kinetic headline reveal ── */
export function StaggerText({ text, className, delay = 0, as: Tag = 'span' }) {
  const words = String(text).split(' ');
  return (
    <Tag className={className} style={{ display: 'inline-block' }}>
      {words.map((w, i) => (
        <span key={i} style={{ display: 'inline-block', overflow: 'hidden', verticalAlign: 'bottom' }}>
          <motion.span
            style={{ display: 'inline-block', whiteSpace: 'pre' }}
            initial={{ y: '110%', rotate: 4, opacity: 0 }}
            animate={{ y: '0%', rotate: 0, opacity: 1 }}
            transition={{ duration: 0.7, delay: delay + i * 0.08, ease: [0.21, 0.6, 0.35, 1] }}
          >
            {w + (i < words.length - 1 ? ' ' : '')}
          </motion.span>
        </span>
      ))}
    </Tag>
  );
}

/* ── Counter: animated number that counts up when scrolled into view ── */
export function Counter({ value, className }) {
  // Parse "50+", "100+", "3", "99.97%", "3.2×", "2,400+" into number + affixes
  const match = String(value).match(/^([^0-9]*)([\d,.]+)(.*)$/);
  const prefix = match ? match[1] : '';
  const numStr = match ? match[2] : '0';
  const suffix = match ? match[3] : '';
  const target = parseFloat(numStr.replace(/,/g, ''));
  const decimals = (numStr.split('.')[1] || '').length;
  const useCommas = numStr.includes(',');

  const ref = useRef(null);
  const inView = useInView(ref, { once: true, amount: 0.6 });
  const mv = useMotionValue(0);
  const spring = useSpring(mv, { duration: 1.6, bounce: 0 });
  const [display, setDisplay] = useState('0');

  useEffect(() => {
    if (inView) mv.set(target);
  }, [inView, target, mv]);

  useEffect(() => {
    const unsub = spring.on('change', (v) => {
      let s = v.toFixed(decimals);
      if (useCommas) s = Number(s).toLocaleString('en-US', { minimumFractionDigits: decimals });
      setDisplay(s);
    });
    return unsub;
  }, [spring, decimals, useCommas]);

  return (
    <span ref={ref} className={className}>
      {prefix}{display}{suffix}
    </span>
  );
}

/* ── Magnetic: element follows the cursor slightly on hover ── */
export function Magnetic({ children, strength = 0.3, className }) {
  const ref = useRef(null);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const sx = useSpring(x, { stiffness: 200, damping: 16 });
  const sy = useSpring(y, { stiffness: 200, damping: 16 });

  const onMove = (e) => {
    const r = ref.current.getBoundingClientRect();
    x.set((e.clientX - (r.left + r.width / 2)) * strength);
    y.set((e.clientY - (r.top + r.height / 2)) * strength);
  };
  const onLeave = () => { x.set(0); y.set(0); };

  return (
    <motion.div
      ref={ref}
      className={className}
      style={{ x: sx, y: sy, display: 'inline-block' }}
      onMouseMove={onMove}
      onMouseLeave={onLeave}
    >
      {children}
    </motion.div>
  );
}

/* ── Tilt: 3D perspective tilt on hover for cards ── */
export function Tilt({ children, max = 6, className }) {
  const ref = useRef(null);
  const rx = useMotionValue(0);
  const ry = useMotionValue(0);
  const srx = useSpring(rx, { stiffness: 220, damping: 18 });
  const sry = useSpring(ry, { stiffness: 220, damping: 18 });

  const onMove = (e) => {
    const r = ref.current.getBoundingClientRect();
    const px = (e.clientX - r.left) / r.width - 0.5;
    const py = (e.clientY - r.top) / r.height - 0.5;
    ry.set(px * max * 2);
    rx.set(-py * max * 2);
  };
  const onLeave = () => { rx.set(0); ry.set(0); };

  return (
    <motion.div
      ref={ref}
      className={className}
      style={{ rotateX: srx, rotateY: sry, transformPerspective: 900 }}
      onMouseMove={onMove}
      onMouseLeave={onLeave}
    >
      {children}
    </motion.div>
  );
}

/* ── CursorGlow: subtle radial glow that follows the pointer ── */
export function CursorGlow() {
  const x = useMotionValue(-400);
  const y = useMotionValue(-400);
  const sx = useSpring(x, { stiffness: 60, damping: 20 });
  const sy = useSpring(y, { stiffness: 60, damping: 20 });

  useEffect(() => {
    const onMove = (e) => { x.set(e.clientX - 250); y.set(e.clientY - 250); };
    window.addEventListener('mousemove', onMove);
    return () => window.removeEventListener('mousemove', onMove);
  }, [x, y]);

  return (
    <motion.div
      aria-hidden
      className="cursor-glow"
      style={{ x: sx, y: sy }}
    />
  );
}

/* ── ParallaxOrb: hero orb that drifts with scroll ── */
export function ParallaxOrb({ className, speed = 0.25 }) {
  const { scrollY } = useScroll();
  const y = useTransform(scrollY, (v) => v * speed);
  return <motion.div aria-hidden className={className} style={{ y }} />;
}

/* ── CodeBackground: faint code that types itself in behind the hero ── */
const CODE_SNIPPET = [
  "import { Storefront } from '@aqualeo/commerce';",
  "",
  "const store = new Storefront({",
  "  platform: 'shopify',",
  "  markets: ['uk', 'ae', 'in'],",
  "  currency: 'auto',",
  "});",
  "",
  "async function deploy(service) {",
  "  const cluster = await k8s.connect(env.CLUSTER);",
  "  await cluster.rollout(service, { replicas: 4 });",
  "  return cluster.health(service);",
  "}",
  "",
  "// staff augmentation — match talent to roadmap",
  "team.assign({",
  "  skills: ['react', 'node', 'python', 'aws'],",
  "  availability: 'immediate',",
  "});",
  "",
  "model.train({ data: pipeline.stream(), epochs: 12 });",
  "db.query`SELECT roi FROM clients WHERE growth > 3`;",
  "",
  "$ git commit -m 'ship smarter, grow faster'",
  "$ aqualeo deploy --env production --region eu-west-2",
  "* build complete · 0 errors · live in 1.4s",
  "",
];

const CODE_SEED = 12;   // start low; the code then builds upward (bottom → top)

/* Shared self-typing engine: seeds a few lines, then types the snippet in,
 * line by line, scrolling upward. Used by CodeBackground + StrategyBackground. */
function useTypedLines(snippet, { seed, max, speed }) {
  const seedLines = () => Array.from({ length: seed }, (_, i) => snippet[i % snippet.length]);
  const [out, setOut] = useState(() => seedLines().join('\n'));
  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setOut(seedLines().join('\n'));
      return;
    }
    const done = seedLines();
    let li = seed, ci = 0, last = 0, raf;
    const tick = (t) => {
      if (t - last >= speed) {
        last = t;
        const line = snippet[li % snippet.length];
        if (ci >= line.length) {
          done.push(line);
          if (done.length > max) done.shift();
          li += 1; ci = 0;
          setOut(done.join('\n'));
        } else {
          ci += 1;
          setOut(done.join('\n') + '\n' + line.slice(0, ci));
        }
      }
      raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  return out;
}

/* Lightweight tokenizer → coloured spans (editor-style syntax highlight) */
const HL_RE = /(\/\/[^\n]*|^[ \t]*\*[^\n]*$)|(`[^`]*`|'[^']*'|"[^"]*")|\b(import|from|const|let|var|async|await|function|return|new|export|if|for|of|in)\b|\b(\d[\w.]*)\b|(\$[^\n]*)|([{}()[\];:,.=<>+\-*/|&])/gm;
function highlight(code) {
  const nodes = [];
  let last = 0, m, k = 0;
  HL_RE.lastIndex = 0;
  while ((m = HL_RE.exec(code)) !== null) {
    if (m.index > last) nodes.push(code.slice(last, m.index));
    const cls = m[1] ? 'tk-comment' : m[2] ? 'tk-string' : m[3] ? 'tk-keyword'
      : m[4] ? 'tk-number' : m[5] ? 'tk-prompt' : 'tk-punct';
    nodes.push(<span key={k++} className={cls}>{m[0]}</span>);
    last = m.index + m[0].length;
    if (m[0].length === 0) HL_RE.lastIndex += 1;
  }
  if (last < code.length) nodes.push(code.slice(last));
  return nodes;
}

export function CodeBackground({ className = '' }) {
  const out = useTypedLines(CODE_SNIPPET, { seed: CODE_SEED, max: 88, speed: 18 });
  return (
    <div className={'code-bg ' + className} aria-hidden="true">
      <pre>{highlight(out)}<span className="code-caret">▋</span></pre>
    </div>
  );
}

/* ── StrategyBackground: a faint strategy / business-analysis blueprint —
 * roadmap, process flow and KPIs — that types itself in behind the hero. ── */
const STRATEGY_SNIPPET = [
  "IT STRATEGY · TARGET OPERATING MODEL",
  "",
  "1. current-state assessment",
  "   • application portfolio & tech debt",
  "   • cloud readiness · security posture",
  "2. vision & guiding principles",
  "   • cloud-first · API-led · data-driven",
  "",
  "[ Discover ] → [ Define ] → [ Design ] → [ Deliver ]",
  "",
  "requirements:",
  "   as-is process  →  gap analysis  →  to-be design",
  "   stakeholder map · RACI · acceptance criteria",
  "",
  "roadmap",
  "   Phase 1  foundation ........... Q1–Q2",
  "   Phase 2  modernisation ........ Q3–Q4",
  "   Phase 3  optimisation ......... Q1+",
  "",
  "KPI  time-to-market      ↓ 40%",
  "KPI  process cycle time  ↓ 35%",
  "KPI  incident MTTR       ↓ 55%",
  "",
  "governance:",
  "   steering board → architecture review → delivery",
  "   risk register · RAID log · benefits realisation",
  "",
  "✓ blueprint approved — value tracked quarterly",
  "",
];

const STRATEGY_SEED = 14;

/* Tokenizer for the strategy blueprint → coloured spans */
const STRAT_RE = /(^[A-Z][A-Z0-9 ·&/-]{5,}$)|(✓)|(\[[^\]]*\]|→|↓)|\b(KPI|Phase\s\d|Q[1-4](?:–Q?[1-4])?)\b|(\d+%)|(•|·|\.{3,}|:)/gm;
function highlightStrategy(text) {
  const nodes = [];
  let last = 0, m, k = 0;
  STRAT_RE.lastIndex = 0;
  while ((m = STRAT_RE.exec(text)) !== null) {
    if (m.index > last) nodes.push(text.slice(last, m.index));
    const cls = m[1] ? 'tk-keyword' : m[2] ? 'tk-string' : m[3] ? 'tk-prompt'
      : m[4] ? 'tk-keyword' : m[5] ? 'tk-number' : 'tk-comment';
    nodes.push(<span key={k++} className={cls}>{m[0]}</span>);
    last = m.index + m[0].length;
    if (m[0].length === 0) STRAT_RE.lastIndex += 1;
  }
  if (last < text.length) nodes.push(text.slice(last));
  return nodes;
}

export function StrategyBackground({ className = '' }) {
  const out = useTypedLines(STRATEGY_SNIPPET, { seed: STRATEGY_SEED, max: 80, speed: 26 });
  return (
    <div className={'code-bg strategy-bg ' + className} aria-hidden="true">
      <pre>{highlightStrategy(out)}<span className="code-caret">▋</span></pre>
    </div>
  );
}

/* ── Stagger container + item for grids ── */
export const staggerParent = {
  hidden: {},
  show: { transition: { staggerChildren: 0.1 } },
};
export const staggerChild = {
  hidden: { opacity: 0, y: 30 },
  show: { opacity: 1, y: 0, transition: { duration: 0.6, ease: [0.21, 0.6, 0.35, 1] } },
};
