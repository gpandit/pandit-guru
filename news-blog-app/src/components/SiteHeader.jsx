import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';

/**
 * Header chrome shared with the main pandit.guru site (nav, logo, theme
 * toggle, mobile menu). Ported into the SPA so /news-blog pages don't look
 * orphaned from the rest of the site. Every other page is a full static
 * page, so every link except "News & Blog" itself is a plain <a> back to
 * pandit.guru — only the current section stays inside the SPA.
 */
export default function SiteHeader() {
  const location = useLocation();
  const [theme, setTheme] = useState(() => {
    try { return localStorage.getItem('theme') || 'dark'; } catch { return 'dark'; }
  });
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('theme', theme); } catch { /* private-browsing etc: theme just won't persist */ }
  }, [theme]);

  const isBlogSection = location.pathname.startsWith('/news-blog');

  return (
    <header className="gp-nav">
      <div className="gp-nav-inner">
        <a href="/" className="gp-logo">
          GURU<span>P<b className="gp-logo-hl">A</b>ND<b className="gp-logo-hl">I</b>T</span>
        </a>
        <nav className="gp-nav-links" aria-label="Primary">
          <a href="/index.html#achievements">Achievements</a>
          <a href="/index.html#experience">Experience</a>
          <a href="/index.html#skills">Skills</a>
          <a href="/projects.html">Projects</a>
          <Link to="/news-blog" aria-current={isBlogSection ? 'page' : undefined}>News &amp; Blog</Link>
        </nav>
        <button
          type="button"
          className="gp-theme-toggle"
          aria-label="Toggle light/dark theme"
          onClick={() => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))}
        >
          <svg className="gp-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
          <svg className="gp-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
        </button>
        <a className="gp-btn gp-btn-ghost gp-nav-cta" href="/index.html#contact">Get in touch</a>
        <button
          type="button"
          className="gp-nav-toggle"
          aria-label="Open menu"
          aria-expanded={menuOpen}
          aria-controls="gp-mobile-menu"
          onClick={() => setMenuOpen((o) => !o)}
        >
          <span></span><span></span><span></span>
        </button>
      </div>
      <div id="gp-mobile-menu" className={'gp-mobile-menu' + (menuOpen ? ' gp-open' : '')}>
        <a href="/index.html#achievements" onClick={() => setMenuOpen(false)}>Achievements</a>
        <a href="/index.html#experience" onClick={() => setMenuOpen(false)}>Experience</a>
        <a href="/index.html#skills" onClick={() => setMenuOpen(false)}>Skills</a>
        <a href="/projects.html" onClick={() => setMenuOpen(false)}>Projects</a>
        <Link to="/news-blog" onClick={() => setMenuOpen(false)}>News &amp; Blog</Link>
        <a className="gp-btn gp-btn-primary" href="/index.html#contact" onClick={() => setMenuOpen(false)}>Get in touch</a>
      </div>
    </header>
  );
}
