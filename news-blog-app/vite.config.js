import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds a small SPA that gets mounted onto the static pandit.guru site at
// the /news-blog and /admin paths (see ../.htaccess). Assets are emitted
// under a dedicated, site-root-relative folder so they never collide with
// the hand-maintained /assets directory used by the rest of the site.
export default defineConfig({
  plugins: [react()],
  base: '/',
  build: {
    outDir: '../news-blog-build',
    assetsDir: 'news-blog-assets',
    emptyOutDir: true,
  },
  server: {
    port: Number(process.env.PORT) || 5174,
  },
});
