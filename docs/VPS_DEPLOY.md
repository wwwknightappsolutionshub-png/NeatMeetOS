# NeatMeet OS VPS Deploy (Production)

**Domain:** `https://neatmeet.prohost.cloud`  
**Server path:** `/www/wwwroot/neatmeet.prohost.cloud`  
**Panel:** aaPanel · **Process manager:** PM2

---

## Production map

| Piece | Detail |
|--------|--------|
| Site directory | `/www/wwwroot/neatmeet.prohost.cloud` |
| Running directory (Laravel public) | `/backend/public` |
| Next.js (PM2 `neatmeet-frontend`) | Port **3006** |
| Laravel queue (PM2 `neatmeet-queue`) | cwd: `.../backend` |
| PHP CLI / FPM | `/www/server/php/83/bin/php` · enable-php-83 |
| Database | PostgreSQL `neatmeet_os` @ `127.0.0.1:5432` |
| Scheduler | cron: `* * * * *` → `artisan schedule:run` |

**Env (must match live domain):**

- Backend `APP_URL` / `FRONTEND_URL` = `https://neatmeet.prohost.cloud`
- Frontend `NEXT_PUBLIC_SITE_URL` / `NEXT_PUBLIC_API_URL` = site + `/api/v1`

---

## CRITICAL — Git safe.directory (required on VPS)

This repo is often owned by a different user than `root`. **Never** run bare `git pull` or `git log` on the VPS — they fail with `fatal: detected dubious ownership`.

**Always prefix every git command** with:

```bash
-c safe.directory=/www/wwwroot/neatmeet.prohost.cloud
```

Examples:

```bash
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud pull origin main
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud log -1 --oneline
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud status
```

---

## CRITICAL — Never rebuild `.next` while Next is online

**Never** run `rm -rf .next` or `npm run build` while `neatmeet-frontend` is serving traffic.

Always:

1. `pm2 stop neatmeet-frontend`
2. install / build
3. `pm2 start neatmeet-frontend`

Rebuilding while online causes stale HTML ↔ missing `/_next/static` chunk 404s.

---

## Later deploys (when you update code)

Always stop the frontend before rebuilding `.next`:

```bash
cd /www/wwwroot/neatmeet.prohost.cloud
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud pull origin main
cd backend && /www/server/php/83/bin/php artisan migrate --force && /www/server/php/83/bin/php artisan config:cache
pm2 stop neatmeet-frontend
cd ../frontend && npm install && rm -rf .next && npm run build
pm2 start neatmeet-frontend
pm2 restart neatmeet-queue
```

If the landing page loads in your browser, you’re done with this deploy.

### Full deploy (when Composer packages also changed)

```bash
cd /www/wwwroot/neatmeet.prohost.cloud
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud pull origin main
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud log -1 --oneline

cd backend
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
/www/server/php/83/bin/php artisan migrate --force
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache

pm2 stop neatmeet-frontend
cd ../frontend
npm install
rm -rf .next
npm run build
pm2 start neatmeet-frontend
pm2 restart neatmeet-queue

curl -sI https://neatmeet.prohost.cloud/ | head -10
curl -s https://neatmeet.prohost.cloud/api/v1/health; echo
```

> **Dependencies:** `node_modules/` and `vendor/` are git-ignored. Run `npm install` and `composer install` after pull whenever lockfiles change.

After deploy:

1. Hard-refresh (Ctrl+Shift+R) or use a private window.
2. Confirm `git log -1` on the VPS matches the expected commit.
3. Purge aaPanel site cache if enabled.

---

## Verify

```bash
pm2 list | grep neatmeet
curl -sI https://neatmeet.prohost.cloud/ | head -15
curl -s https://neatmeet.prohost.cloud/api/v1/health; echo
ss -tlnp | grep ':3006'
```

Expect:

- `neatmeet-frontend` and `neatmeet-queue` **online**
- HTTPS `/` → `200` (Next.js HTML)
- `/api/v1/health` → `{"success":true,... database ok ...}`

---

## Nginx (aaPanel)

- **Site directory:** `/www/wwwroot/neatmeet.prohost.cloud`
- **Running directory:** `/backend/public`
- Proxy `/` and `/_next/static/` → `http://127.0.0.1:3006`
- Route `/api/` → Laravel `index.php` (PHP 8.3)
- Keep aaPanel `#CERT-APPLY-CHECK` / SSL blocks intact
- Force HTTPS via aaPanel SSL after DNS A record points to the VPS

Reload after config edits:

```bash
nginx -t && nginx -s reload
```

---

## Scheduler cron (once)

```bash
* * * * * cd /www/wwwroot/neatmeet.prohost.cloud/backend && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## Optional — permanent safe.directory

```bash
git config --global --add safe.directory /www/wwwroot/neatmeet.prohost.cloud
```

Prefer the `-c safe.directory=...` flag in deploy snippets unless you want this permanent fix.
