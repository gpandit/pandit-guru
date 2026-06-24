# CI/CD Dashboard

A small password-protected PHP page showing recent GitHub Actions runs for a
configurable list of repos, with the ability to trigger `workflow_dispatch`
runs. Designed to run on plain shared PHP hosting (Hostinger) — no
Composer, no background workers. User accounts live in a MySQL `users` table.

## Deploy to Hostinger

1. Upload this whole `ci-dashboard/` folder to your site, e.g.
   `public_html/ci-dashboard/`.
2. In Hostinger's hPanel, create a MySQL database (Databases > MySQL
   Databases) and note the host/name/user/password it gives you.
3. SSH in (or use Hostinger's File Manager "Edit") and create `config.php`
   from the template:
   ```
   cp config.sample.php config.php
   ```
   Fill in the GitHub token and the `db` block with your MySQL credentials
   from step 2. Also set `seed_admin_username` / `seed_admin_password` to
   whatever you want the first admin login to be — pick your own password,
   don't reuse an example from anywhere.
4. Create a GitHub PAT (fine-grained, scoped only to the repos you want to
   track) with the **Actions** repository permission set to **Read and
   write**. Read lets the dashboard list runs; write is what lets the
   "Run workflow" button call `workflow_dispatch`.
5. Run the migration once to create the `users` table and seed the first
   admin account from `config.php`'s `seed_admin_username`/`seed_admin_password`:
   - If you have SSH: `php migrate.php`
   - If you don't have SSH: temporarily remove `migrate.php` from the
     `.htaccess` deny list, visit `https://yourdomain/ci-dashboard/migrate.php`
     once over HTTPS, copy the output, then **restore the deny rule**.
   It prints a TOTP secret + `otpauth://` URI for that account — shown
   once, not retrievable afterward. It refuses to run again once the
   `users` table has any rows, so the seed password only matters once.
6. Scan/enter that `otpauth://` URI into your authenticator app (Google
   Authenticator, 1Password, Authy, etc.) — manual entry only, the secret is
   never sent to a third-party QR service.
7. Visit `https://yourdomain/ci-dashboard/` and log in with the username,
   password, and TOTP code from steps 3 and 6.
8. From the dashboard's "Admin" link, that account can create
   further users — each gets their own freshly generated TOTP secret.

## Day-to-day use

- **Add/remove repos**: use the "Tracked repos" panel on the dashboard —
  stored in `repos.json` (must be writable by PHP, default permissions
  from upload are fine).
- **Add/remove users**: admins see an "Admin" link in the dashboard header,
  leading to `admin.php`. Adding a user generates a fresh TOTP secret shown
  once on screen — hand it to the new user immediately, it isn't
  retrievable afterward. Users are stored in the `users` MySQL table.
- **Status table**: pulls `GET /repos/{owner}/{repo}/actions/runs` per
  tracked repo, cached on disk in `cache/` for `cache_ttl_seconds`
  (default 60s) to stay well under GitHub's rate limits.
- **Trigger a deploy**: pick a workflow + branch under "Trigger a
  deployment" and click "Run workflow". Only workflows with a
  `workflow_dispatch:` trigger in their YAML show up / can be run this way.

## Deploying at pandit.guru/admin instead of /ci-dashboard

The app doesn't care what folder it lives in. To make it reachable at
`pandit.guru/admin`, just upload the contents of `ci-dashboard/` to
`public_html/admin/` instead (note: this app's own internal `admin.php`
user-management page would then be at `pandit.guru/admin/admin.php`).

## Security notes

- `config.php`, `repos.json`, and `migrate.php` are denied directly via
  `.htaccess` — only reachable through the PHP front controllers.
- `cache/` is denied via `.htaccess` since it holds raw GitHub API
  responses (commit SHAs, branch names).
- Login is two steps: username + password first, then a live TOTP code on a
  separate screen; failed attempts are throttled (5 per rolling 60s window,
  per session) and a pending password step expires after 5 minutes.
- Only admin users can reach `admin.php` to add/remove users; the last
  remaining admin and your own currently-logged-in account can't be removed.
- Session cookies are `HttpOnly`, `Secure`, `SameSite=Lax`; the session ID
  is regenerated on successful login.
- All state-changing actions (add/remove repo, trigger deploy) require a
  CSRF token tied to the session.
