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
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud log -1 --oneline
cd backend
/www/server/php/83/bin/php artisan migrate --force
/www/server/php/83/bin/php artisan route:clear
/www/server/php/83/bin/php artisan config:cache
/www/server/php/83/bin/php artisan route:cache
pm2 stop neatmeet-frontend
cd ../frontend && npm install && rm -rf .next && npm run build
pm2 start neatmeet-frontend
pm2 restart neatmeet-queue
```

> **Route cache:** Always `route:clear` then `route:cache` after pull. Stale route cache makes new endpoints (e.g. `/platform/whatsapp-settings`) return HTML 404 and breaks the platform settings UI.

If the landing page loads in your browser, you’re done with this deploy.

### Frontend performance (nginx)

Ensure long cache for hashed Next assets and gzip/brotli in aaPanel for the site:

```nginx
location /_next/static/ {
    proxy_pass http://127.0.0.1:3006;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

gzip on;
gzip_types text/css application/javascript application/json image/svg+xml;
```

After env changes to `NEXT_PUBLIC_*`, always **stop** `neatmeet-frontend` before `npm run build` so clients never get stale HTML pointing at missing chunks.

### Full deploy (when Composer packages also changed)

```bash
cd /www/wwwroot/neatmeet.prohost.cloud
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud pull origin main
git -c safe.directory=/www/wwwroot/neatmeet.prohost.cloud log -1 --oneline

cd backend
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
/www/server/php/83/bin/php artisan migrate --force
/www/server/php/83/bin/php artisan route:clear
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

## AI Hairstyle Preview (premium module)

Super-admin enables the module per eligible tenant (barbershop / barber / boutique / chain / spa). Generation uses a queue job — **`neatmeet-queue` must be online**.

Add to **backend** `.env` (then `config:cache`). **Production must disable stub:**

```bash
AI_HAIRSTYLE_PROVIDER=replicate
AI_HAIRSTYLE_ALLOW_STUB=false
AI_HAIRSTYLE_TEMP_MAX_AGE_MINUTES=120
REPLICATE_API_TOKEN=r8_...
REPLICATE_AI_HAIRSTYLE_MODEL=zsxkib/instant-id
```

| Key | Notes |
|-----|--------|
| `AI_HAIRSTYLE_PROVIDER` | Default when no platform settings row exists (`stub` or `replicate`) |
| `AI_HAIRSTYLE_ALLOW_STUB` | **Set `false` on VPS.** Blocks stub selection and generation (fail-closed) |
| `AI_HAIRSTYLE_TEMP_MAX_AGE_MINUTES` | Orphan selfie purge window (hourly `ai-hairstyle:purge-temp`) |
| `REPLICATE_API_TOKEN` | Required before enabling Replicate in `/platform/settings` |
| `REPLICATE_AI_HAIRSTYLE_MODEL` | Optional override (default InstantID) |

**After migrate + env:**

1. Platform → **Settings** → AI Hairstyle provider → **Replicate** (stub hidden when `ALLOW_STUB=false`)  
2. Platform → tenant modules → **Force on** AI Hairstyle Preview (starts 30-day module trial)  
3. Confirm `pm2 restart neatmeet-queue` after deploy so `GenerateAiHairstyleJob` runs  
4. Confirm scheduler runs (hourly temp purge)  
5. Smoke: `/book/{slug}` landing → `/ai-look` → submit (owner bell notice) → admin **Approved Looks** → accept (client email)

Selfies are ephemeral (local temp, deleted after the job or by hourly purge). Only composite previews are stored on the public disk. Accept emails the guest; submit notifies salon owners via the admin bell.

---

## Cloudflare Turnstile + IP bans (public / auth writes)

Visible Turnstile captcha on login, magic link, forgot/reset password, signup, public booking / join / member-login / shop writes. Auto IP bans for repeated Turnstile failures, failed logins, honeypot trips, and excess 429s.

Add to **backend** `.env` and **frontend** `.env` / PM2 env (same site key), then `config:cache` and rebuild frontend:

```bash
# backend
TURNSTILE_SITE_KEY=0x4AAAAA...
TURNSTILE_SECRET_KEY=0x4AAAAA...
TURNSTILE_ENABLED=true

# frontend (Next.js build-time)
NEXT_PUBLIC_TURNSTILE_SITE_KEY=0x4AAAAA...
```

Create keys in the Cloudflare dashboard → Turnstile (widget type: **Managed** — shows a visible checkbox/challenge). Add every production hostname (`neatmeetos.com`, `www.neatmeetos.com`, etc.) under the widget’s allowed domains.

Ops unban:

```bash
cd /www/wwwroot/neatmeet.prohost.cloud/backend
/www/server/php/83/bin/php artisan security:unban-ip 1.2.3.4
```

Migration `ip_bans` runs with the normal `artisan migrate --force` deploy step.

---

## Post-deploy tenant workspace welcomes (one-shot)

Queue catch-up welcome **email + WhatsApp** for tenants listed in `backend/config/post_deploy_welcomes.php`. Run **once** after deploy (not on every deploy):

```bash
cd /www/wwwroot/neatmeet.prohost.cloud/backend
/www/server/php/83/bin/php artisan tenants:queue-workspace-welcomes --delay=5
```

Requires `neatmeet-queue` running. Messages send ~5 minutes after the command runs. Use `--dry-run` to preview recipients without queueing.

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

## Scheduler cron (once — required for T-20 SOS + reminders)

```bash
* * * * * cd /www/wwwroot/neatmeet.prohost.cloud/backend && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1
```

Verify the crontab exists and Laravel sees scheduled jobs:

```bash
crontab -l | grep schedule:run
cd /www/wwwroot/neatmeet.prohost.cloud/backend
/www/server/php/83/bin/php artisan schedule:list
```

Expect `booking:dispatch-approaching-sos` every minute (raises staff SOS ~20 minutes before appointments), plus booking reminders / marketing / analytics jobs.

Manual smoke:

```bash
/www/server/php/83/bin/php artisan booking:dispatch-approaching-sos --lead=20 --window=2
```

---

## Platform WhatsApp (Genius)

Configure in **Platform → Settings → WhatsApp outbound (Genius)** (not in git):

| Field | Example |
|-------|---------|
| Enabled | on |
| Provider | `genius` |
| API key | from Genius dashboard (`x-api-key`) |
| Session ID | `session_…` |
| Base URL | `https://restapi.geniusdevel.com` |

Optional env fallbacks (backend `.env`):

```bash
WHATSAPP_PROVIDER=genius
WHATSAPP_GENIUS_API_KEY=
WHATSAPP_GENIUS_SESSION_ID=
WHATSAPP_GENIUS_BASE_URL=https://restapi.geniusdevel.com
```

Use **Send test message** with an E.164 phone after saving. Booking WhatsApp delivery also requires the guest to opt in on `/book/{slug}` (`allow_whatsapp`).

**Member PWA login OTP** also uses this Genius path (`member.portal_otp`). Enable WhatsApp before expecting `/member/{slug}` OTP login to work in production.

Owner SOS push needs VAPID keys + the admin “Enable SOS push” prompt:

```bash
VAPID_SUBJECT=mailto:ops@example.com
VAPID_PUBLIC_KEY=…
VAPID_PRIVATE_KEY=…
```

---

## Optional — permanent safe.directory

```bash
git config --global --add safe.directory /www/wwwroot/neatmeet.prohost.cloud
```

Prefer the `-c safe.directory=...` flag in deploy snippets unless you want this permanent fix.
