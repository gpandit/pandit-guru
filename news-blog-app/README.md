# News & Blog (pandit.guru)

React/Vite SPA ported from `aqualeo-digecom`'s blog feature, mounted onto the
static pandit.guru site at `/news-blog` (public) and `/admin` (content admin —
posts, authors, comments, analytics, users).

## Architecture

- This app is the only part of pandit.guru that's a build step. The rest of
  the site is plain static HTML/CSS/JS, unchanged.
- The PHP/MySQL backend lives at the site root: `content-api/`, `admin-api/`,
  `lib/`, `image.php`, `blog-lead-handler.php`, `phpmailer/`.
- `../.htaccess` rewrites any request under `/news-blog` or `/admin` to
  `../blog-app.html` (the built SPA shell) unless it's a real file. The SPA's
  client-side router then decides what to render. Vite's `base: '/'` plus a
  dedicated `assetsDir` (`news-blog-assets`) means the one shell file works
  identically no matter which of the two paths served it.

## Setup (one-time, on the server)

1. Create an empty MySQL/MariaDB database + user. Tables are created
   automatically on first request — no schema file to import.
2. Copy `../lib/.env.example` to `../lib/.env` and fill in real values
   (DB credentials, `ADMIN_PASSWORD_HASH`, `ENCRYPTION_KEY`,
   `BLIND_INDEX_KEY`, SMTP creds for admin invite/reset emails). See the
   comments in that file for how to generate each secret.
3. `lib/.env` is gitignored — never commit it.

## Rebuilding after a source change

```
cd news-blog-app
npm install   # first time only
npm run build # outputs to ../news-blog-build
cp ../news-blog-build/index.html ../blog-app.html
rm -rf ../news-blog-assets && cp -r ../news-blog-build/news-blog-assets ../news-blog-assets
rm -rf ../news-blog-build
```

## Local preview

`php -S localhost:8745 ../.dev-router.php` from the site root mimics the
`.htaccess` rewrite for local testing (the router is dev-only, not deployed).
Without a real database configured, public/admin pages will render but API
calls will show a "Server configuration error" — that's expected until
`lib/.env` is set up.
