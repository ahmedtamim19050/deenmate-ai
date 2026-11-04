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

Add a specific `A` record for `deen` pointing at the VPS address (and `AAAA` if it has IPv6). A specific record takes priority over the wildcard, so nothing else in the zone needs to change. Check the zone for an existing `deen` record first.

### Behind Cloudflare's proxy

If the record is proxied (orange cloud):

- Set SSL/TLS mode to **Full (strict)**, with a valid certificate on the VPS. Other modes can cause redirect loops or leave the last hop unencrypted.
- Put Cloudflare's published ranges (https://www.cloudflare.com/ips/) in `TRUSTED_PROXIES`. Otherwise every visitor appears to come from Cloudflare and they all share one rate limit. Only list these if the VPS accepts web traffic solely from Cloudflare, or a client could forge its address.
- Set a Cloudflare rule to bypass caching for `/api/*` and `/admin*`. Personal API responses must never be cached.
- Cloudflare's default 100 second timeout can cut off a slow AI answer. Keep `AI_REQUEST_TIMEOUT` below it.

## First deploy

```bash
cd /var/www/deenmate-ai/backend
composer install --no-dev --optimize-autoloader
cp .env.production.example .env     # then fill in the blanks
php artisan key:generate
php artisan migrate --seed --force  # on a NEW database only
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
- [ ] `TRUSTED_PROXIES` is set if you are behind a load balancer, so rate limits see real addresses
- [ ] `/admin` restricted by network or VPN if possible. Admin accounts use strong, unique passwords. The panel has no two-factor sign-in yet, so consider enabling Filament's multi-factor authentication before launch
- [ ] Database and storage backups, encrypted, with restore tested
- [ ] A privacy policy that names your AI provider and ad network, and terms of use. Add both links in **Application Settings**
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
