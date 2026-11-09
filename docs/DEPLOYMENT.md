# Deployment

## Production addresses

| What | URL |
| --- | --- |
| Website | https://deen.sohojware.dev |
| Admin | https://deen.sohojware.dev/admin |
| API | https://deen.sohojware.dev/api/v1 |

The API's version prefix is part of its routes, so clients use `/api/v1`, not `/api`.

## Server

PHP 8.4 with `bcmath, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip`. MySQL 8, nginx, certbot and supervisor. Redis is optional.

Ready-made configs are in `deploy/`. They assume the project is at `/var/www/deenmate-ai`.

## DNS

`sohojware.dev` uses Cloudflare DNS, and any unknown subdomain currently resolves through a wildcard to Cloudflare's addresses. So `deen.sohojware.dev` already answers, but **that is not this app**. A resolving name or a 200 response proves nothing until `/api/v1/config` returns JSON from this API.

The expected record is `A`, name `deen`, content the confirmed VPS IPv4 address (and `AAAA` only if the VPS has IPv6). A specific record takes priority over the wildcard, so nothing else in the zone needs to change. Check the zone for an existing `deen` record first; do not delete/replace an existing record or change its proxy status without approval.

### Behind Cloudflare's proxy

If the record is proxied (orange cloud):

- Set SSL/TLS mode to **Full (strict)**, with a valid certificate on the VPS. Other modes can cause redirect loops or leave the last hop unencrypted.
- Set `TRUSTED_PROXIES` to Cloudflare's published ranges (https://www.cloudflare.com/ips/) only if the VPS firewall accepts web traffic solely from Cloudflare. This allows Laravel to honor forwarded client IP and HTTPS headers without trusting forged headers from direct clients.
- Set Cloudflare cache rules to bypass `/api/v1/*`, `/admin`, and `/admin/*`. Personal API responses and authenticated admin pages must never be cached.
- Cloudflare's default 100 second timeout can cut off a slow AI answer. Keep `AI_REQUEST_TIMEOUT` below it.
- Use a Cloudflare Origin CA certificate only when the hostname remains proxied; otherwise use a publicly trusted Let's Encrypt certificate. Keep the private key on the VPS and point Nginx at the installed certificate paths.

## Database and migrations

The production template uses MySQL on `127.0.0.1`, a dedicated `deenmate` database/user, and an empty password field that must be filled with a strong, unique server-generated credential. Create the database user with access only to that database and host `localhost`; do not reuse any local credentials. Keep MySQL bound to loopback and do not open port 3306 to the internet.

The current migrations create the app's account, activity, points, prayer, AI, learning, system, cache, queue, and Sanctum-token tables. Two migrations add MySQL full-text indexes. Review these migrations against the target MySQL version and confirm the database is new before applying the initial schema; each `down()` path drops application tables and must not be used to roll back production casually. No migrations are run as part of this preparation.

Before any production migration, take a consistent database backup, encrypt it, store a copy off-server, retain daily backups for at least 30 days, and verify restoration to a separate staging database. For existing production data, review a dry-run and the exact pending migration list, then obtain an explicit maintenance window and approval. Never copy the local database or credentials to production.

## Queues and scheduler

The current production template uses the database queue and cache, so Redis is optional. If Redis is installed, enable the existing Laravel Redis configuration with `QUEUE_CONNECTION=redis` and/or `CACHE_STORE=redis`, install/enable the PHP Redis extension, bind Redis to loopback, and configure a strong local ACL password. Do not expose Redis publicly. Failed jobs use the `failed_jobs` database table and are pruned after seven days by the existing daily scheduler.

The worker processes `BuildDataExport` jobs; the schedule prunes expired exports hourly and expired Sanctum tokens and failed jobs daily. Install the supplied Supervisor and cron files only after checking PHP paths, permissions, and service availability on the VPS. After deploying queue-related changes, `php artisan queue:restart` lets the worker finish its current job and exit; Supervisor then restarts it. This command is a future server procedure and was not run here.

## First deploy

```bash
cd /var/www/deenmate-ai/backend
composer install --no-dev --optimize-autoloader
cp .env.production.example .env     # then fill in the blanks
php artisan key:generate
# Review and approve the migration plan before running migrations on the VPS.
php artisan migrate --seed --force  # only on a verified NEW database
php artisan filament:assets
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan quran:import
php artisan hadith:import nawawi
php artisan deenmate:make-admin you@example.com --role=super_admin
chown -R www-data:www-data storage bootstrap/cache
```

On a database that already holds data, use `php artisan migrate --force` without `--seed`, and never `migrate:fresh` or `db:wipe`.

To bring local Quran, translation and Hadith data across rather than importing again, dump only those tables. Do not copy local users or `.env` to the server.

## Web server and TLS

```bash
sudo cp deploy/nginx/deen.sohojware.dev.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/deen.sohojware.dev.conf /etc/nginx/sites-enabled/
sudo certbot certonly --webroot -w /var/www/html -d deen.sohojware.dev
sudo nginx -t && sudo systemctl reload nginx
```

Behind Cloudflare, either set the record to DNS only for the first request, or use certbot's DNS challenge or a Cloudflare Origin Certificate. The first `certbot` run needs the port 80 block live. If nginx refuses the config because the certificate does not exist yet, comment out the 443 block for that run. The web root must be `backend/public`, never the project root.

## Keep these running

```bash
sudo cp deploy/supervisor/deenmate-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/cron/deenmate /etc/cron.d/deenmate
```

The worker runs data exports, and the scheduler prunes expired exports and tokens.

## Check it

```bash
curl -I https://deen.sohojware.dev/up
curl https://deen.sohojware.dev/api/v1/config  # must be JSON containing ai_configured
curl -I https://deen.sohojware.dev/.env        # must be 403 or 404
```

## Security checklist

- [ ] HTTPS only. `APP_URL` is `https://deen.sohojware.dev`, and `SESSION_SECURE_COOKIE=true`
- [ ] `APP_DEBUG=false`
- [ ] A dedicated database user with only this database's rights
- [ ] `.env` is not web accessible and not in git
- [ ] `storage/app/private` is not web accessible. Exports live there and are only streamed to their owner
- [ ] `CORS_ALLOWED_ORIGINS` is the production website origin only; authenticated API routes do not use wildcard CORS
- [ ] `TRUSTED_PROXIES` is empty for direct traffic, or contains only the actual trusted proxy ranges when origin traffic is firewalled to those proxies
- [ ] `/admin` restricted by network or VPN if possible. Admin accounts use strong, unique passwords. The panel has no two-factor sign-in yet, so consider enabling Filament's multi-factor authentication before launch
- [ ] Database and private-storage backups are encrypted, stored off-server, and restore-tested
- [ ] A privacy policy that names your AI provider, and terms of use. Add both links in **Application Settings**
- [ ] A real support address in **Application Settings**

## Admin roles

| Role | Can do | Cannot do |
| --- | --- | --- |
| Super Administrator | Everything in the panel, assign roles, adjust points | See worship records, which have no admin screen |
| System Administrator | Users, rules, settings, AI prompts, usage, audit logs | Approve sources, assign roles |
| Content Editor | Courses, lessons, quizzes, achievements, source drafts | Approve content, see users or audit logs |
| Scholar Reviewer | Approve or reject sources and lessons, AI feedback | Manage users, settings or security |
| Support Staff | Look up users, see privacy request status | Change users, see settings or any worship data |

Privileged changes are written to `audit_logs`, shown in **Audit Logs**.

## Updates

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Point ledger rows are permanent by design, so never edit them by hand. Use **Adjust points** on a user, which adds a new row.

## Local development on Laragon

The project is at `C:\laragon\www\deenmate-ai`, served at http://deenmate-ai.test.

Laragon's auto virtual hosts serve a project's own `public` folder if it has one, and the project root if it does not. Laravel is in `backend/`, so the root `public` junction points at `backend/public`. **Do not remove it**, or Laragon would publish the whole project, including `.env`. If it is ever missing (a fresh clone, for example), recreate it, then use Menu > Apache > Reload:

```bat
cd C:\laragon\www\deenmate-ai
mklink /J public backend\public
```

Local settings live in `backend/.env`, which is git-ignored. Production settings live only on the server. Exports need `php artisan queue:work` running in `backend/`.

Laragon's PHP may not have a CA bundle configured. Point `curl.cainfo` and `openssl.cafile` in `php.ini` at `C:\laragon\etc\ssl\cacert.pem`, or the importers fail with cURL error 60.
