/** Footer chrome shared with the main pandit.guru site. See SiteHeader for context. */
export default function SiteFooter() {
  return (
    <footer className="gp-footer">
      <div className="gp-footer-inner">
        <span>© {new Date().getFullYear()} Guru Pandit</span>
        <span className="gp-muted">IT Program &amp; Delivery Leadership · Tech Strategy</span>
        <span className="gp-footer-legal">
          <a href="/privacy.html">Privacy</a>
        </span>
        <div className="gp-footer-socials">
          <a href="https://x.com/gurupandit" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2.5h3.3l-7.2 8.2 8.5 10.8h-6.6l-5.2-6.6-5.9 6.6H2.5l7.7-8.8L2 2.5h6.8l4.7 6.1 5.4-6.1zm-2.3 17.2h1.8L7.5 4.2H5.6l11 15.5z" /></svg>
          </a>
          <a href="https://instagram.com/gurupandit" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 2.01.24 2.71.51.73.28 1.28.66 1.84 1.22.56.56.94 1.11 1.22 1.84.27.7.46 1.54.51 2.71.06 1.25.07 1.65.07 4.85s0 3.6-.07 4.85c-.05 1.17-.24 2.01-.51 2.71-.28.73-.66 1.28-1.22 1.84-.56.56-1.11.94-1.84 1.22-.7.27-1.54.46-2.71.51-1.25.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.17-.05-2.01-.24-2.71-.51a4.92 4.92 0 0 1-1.84-1.22 4.92 4.92 0 0 1-1.22-1.84c-.27-.7-.46-1.54-.51-2.71C2.21 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.05-1.17.24-2.01.51-2.71.28-.73.66-1.28 1.22-1.84A4.92 4.92 0 0 1 5.84 1.38c.7-.27 1.54-.46 2.71-.51C9.8 1.21 10.2 1.2 12 1.2zm0 1.98c-3.14 0-3.52 0-4.76.06-1.05.05-1.61.23-1.99.38-.5.19-.86.43-1.24.81-.38.38-.62.74-.81 1.24-.15.38-.33.94-.38 1.99-.06 1.24-.06 1.62-.06 4.76s0 3.52.06 4.76c.05 1.05.23 1.61.38 1.99.19.5.43.86.81 1.24.38.38.74.62 1.24.81.38.15.94.33 1.99.38 1.24.06 1.62.06 4.76.06s3.52 0 4.76-.06c1.05-.05 1.61-.23 1.99-.38.5-.19.86-.43 1.24-.81.38-.38.62-.74.81-1.24.15-.38.33-.94.38-1.99.06-1.24.06-1.62.06-4.76s0-3.52-.06-4.76c-.05-1.05-.23-1.61-.38-1.99a3.05 3.05 0 0 0-.81-1.24 3.05 3.05 0 0 0-1.24-.81c-.38-.15-.94-.33-1.99-.38-1.24-.06-1.62-.06-4.76-.06zm0 3.37a5.45 5.45 0 1 1 0 10.9 5.45 5.45 0 0 1 0-10.9zm0 1.98a3.47 3.47 0 1 0 0 6.94 3.47 3.47 0 0 0 0-6.94zm6.94-2.2a1.31 1.31 0 1 1-2.62 0 1.31 1.31 0 0 1 2.62 0z" /></svg>
          </a>
          <a href="https://linkedin.com/in/gurupandit" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.02-3.03-1.85-3.03-1.85 0-2.14 1.45-2.14 2.94v5.66H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.38-1.85 3.61 0 4.28 2.38 4.28 5.47v6.27zM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.12 20.45H3.56V9h3.56v11.45z" /></svg>
          </a>
        </div>
      </div>
    </footer>
  );
}
