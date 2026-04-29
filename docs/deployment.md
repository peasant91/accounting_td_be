# Deployment Guide — accounting-backend

Operator runbook for `accounting-backend` (Laravel 12 + PHP 8.4). Covers initial server setup, the Jenkins-driven CI/CD pipeline, manual fallback, background services, logs, and rollback.

The frontend has its own deploy guide at `accounting-frontend/docs/deployment.md`.

---

## Stack at a glance

- **Runtime:** PHP 8.4 (server uses Plesk-style versioned binaries: `php8.4`, `composer8.4`)
- **Framework:** Laravel 12, Sanctum SPA auth
- **DB:** SQLite by default; production should use MySQL/PostgreSQL — set via `DB_*` env vars
- **Sessions / Cache / Queue:** all `database` driver (no Redis required)
- **Mail:** SMTP (Gmail by default; swap for production SMTP)
- **Web server:** PHP-FPM behind Nginx (recommended)
- **Process supervision:** Supervisor (queue worker), Cron (scheduler)
- **CI/CD:** Jenkins pipeline (`Jenkinsfile`) → SSH rsync + `scripts/deploy.sh`

---

## 1. Prerequisites

On the deployment server:

```bash
# PHP 8.4 with required extensions
sudo apt install php8.4 php8.4-{cli,fpm,mbstring,xml,curl,zip,sqlite3,mysql,bcmath,gd,intl}

# Composer (matching PHP version)
# Either system composer + alias, or version-managed composer8.4 binary

# Nginx (or Apache + PHP-FPM)
sudo apt install nginx

# Supervisor (queue worker)
sudo apt install supervisor

# rsync + ssh (Jenkins deploy mechanism)
sudo apt install rsync openssh-server

# (optional) MySQL if not using SQLite
sudo apt install mysql-server
```

---

## 2. Initial server setup (one-time)

### 2.1 Create deploy target dirs

The `Jenkinsfile` expects three directories — one per environment:

```bash
sudo mkdir -p /var/www/accounting-backend/{develop,staging,production}
sudo chown -R deploy:www-data /var/www/accounting-backend
```

(Adjust `/var/www/...` to match the values stored in Jenkins credentials `development-directory`, `staging-directory`, `production-directory`.)

### 2.2 SSH key for Jenkins

Jenkins uses an SSH key (credential ID `jenkins`) to rsync into the server. Add the public key to `~/.ssh/authorized_keys` of the deploy user.

### 2.3 First-time deploy bootstrap

For each environment dir, do a one-time setup:

```bash
cd /var/www/accounting-backend/production
cp .env.example .env             # then edit with real production values (see §3)
php8.4 artisan key:generate
php8.4 artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# If using MySQL/Postgres, create the DB first, then:
php8.4 artisan migrate --force
```

### 2.4 Nginx site (example)

```nginx
server {
    listen 80;
    server_name api.accounting.example.com;
    root /var/www/accounting-backend/production/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 20M;  # for invoice attachments / payment proofs
}
```

Enable HTTPS via certbot or your existing TLS setup. Sanctum SPA cookies require HTTPS in production (see §3 `SESSION_SECURE_COOKIE`).

---

## 3. Environment variables

Production `.env` keys that **must not be left at defaults**:

```bash
APP_NAME=AccountingTimedoor
APP_ENV=production
APP_DEBUG=false                                  # CRITICAL: never true in prod
APP_KEY=<from `php artisan key:generate`>
APP_URL=https://api.accounting.example.com

# Database — switch from SQLite to MySQL/Postgres for production
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accounting_prod
DB_USERNAME=accounting
DB_PASSWORD=<strong-secret>

# Frontend origin → must match the Next.js public URL
FRONTEND_URL=https://accounting.example.com
SANCTUM_STATEFUL_DOMAINS=accounting.example.com

# Cookies — production SPA auth requires Secure cookies + same site policy
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=.accounting.example.com           # leading dot for subdomain sharing

# Mail — replace Gmail dev creds with production SMTP
MAIL_MAILER=smtp
MAIL_HOST=<production-smtp>
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-pass>
MAIL_ENCRYPTION=tls

# Logging
LOG_LEVEL=warning                                # quieter in prod; "info" if debugging
LOG_CHANNEL=stack

# Currency conversion baseline
BILLING_BASE_CURRENCY=IDR
```

`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK` can stay at their defaults (`database` / `local`) for an internal-tool deployment. Move queue to Redis/SQS only if throughput becomes an issue.

After any `.env` change:

```bash
php8.4 artisan config:clear && php8.4 artisan cache:clear
sudo supervisorctl restart accounting-queue:*    # queue worker reloads .env
```

---

## 4. CI/CD — Jenkins pipeline

The `Jenkinsfile` at the repo root drives all automated deploys. Branch → environment mapping:

| Branch | Target dir | Env |
|---|---|---|
| `develop` | `$DEV_DIR` | development |
| `staging` | `$STAGING_DIR` | staging |
| `main` | `$PRODUCTION_DIR` | production |

Other branches → pipeline aborts with `Unsupported branch`.

### 4.1 Jenkins credentials required

Configure these in *Manage Jenkins → Credentials* before the pipeline can run:

| ID | Type | Purpose |
|---|---|---|
| `jenkins` | SSH Username with private key | Pushes code to server via rsync/ssh |
| `host-ip` | Secret text | Server IP |
| `user-server` | Secret text | SSH user |
| `development-directory` | Secret text | Path on server for develop branch |
| `staging-directory` | Secret text | Path on server for staging branch |
| `production-directory` | Secret text | Path on server for main branch |
| `discord-webhook-url` | Secret text | Webhook for build notifications |
| `mention-discord-id` | Secret text | Discord user/role ID to ping on success/failure |

### 4.2 What a deploy run does

1. **Trigger:** push to `develop` / `staging` / `main`. Jenkins runs `pipeline { ... }`.
2. **Resolve target dir** from branch name.
3. **`rsync` source → server:** `--update --checksum -zrSlhp --exclude-from=.gitignore`. Skips `vendor/`, `.env`, anything in `.gitignore`.
4. **Run `scripts/deploy.sh` on server** (cd-into-target-dir then exec):
   - `composer8.4 install --no-dev`
   - `php8.4 artisan storage:link`
   - `php8.4 artisan migrate --force`
   - `php8.4 artisan config:clear && cache:clear`
5. **Discord notification** on success or failure with last 5 commit messages.

The deploy is **not zero-downtime** — `migrate --force` runs during the active request window. For internal use this is fine; if you ever need zero-downtime, see §8.

### 4.3 Triggering a deploy

```bash
git push origin main             # → production
git push origin staging          # → staging
git push origin develop          # → dev
```

Watch progress in Jenkins UI; Discord channel pings on completion.

---

## 5. Manual deploy (fallback)

When Jenkins is down or you need to deploy a specific commit:

```bash
# On your laptop
git checkout <ref>
rsync --stats --update --checksum -zrSlhp \
      --exclude-from=.gitignore \
      -e "ssh -p 22" \
      ./ deploy@<server>:/var/www/accounting-backend/production/

# Then on the server
ssh deploy@<server>
cd /var/www/accounting-backend/production
bash scripts/deploy.sh
sudo supervisorctl restart accounting-queue:*
```

If `composer install` fails because PHP extensions are missing, install them and retry. Don't `--ignore-platform-reqs` in production.

---

## 6. Background services

### 6.1 Queue worker (Supervisor)

Create `/etc/supervisor/conf.d/accounting-queue.conf`:

```ini
[program:accounting-queue]
process_name=%(program_name)s_%(process_num)02d
command=php8.4 /var/www/accounting-backend/production/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/accounting-queue.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start accounting-queue:*
sudo supervisorctl status accounting-queue:*
```

After every deploy, restart workers so they pick up the new code (`scripts/deploy.sh` does NOT do this; do it from a Jenkins post-deploy hook or by adding to the script):

```bash
sudo supervisorctl restart accounting-queue:*
```

### 6.2 Scheduler (cron)

Recurring invoices (`invoices:process-recurring`) and any other scheduled tasks need Laravel's scheduler running every minute. Add to the deploy user's crontab (`crontab -e`):

```cron
* * * * * cd /var/www/accounting-backend/production && php8.4 artisan schedule:run >> /dev/null 2>&1
```

The recurring-invoice schedule is configured in `routes/console.php` at `dailyAt('00:00')->timezone('Asia/Makassar')`.

---

## 7. Logs & troubleshooting

| What | Where |
|---|---|
| Laravel application log | `storage/logs/laravel.log` |
| Queue worker | `/var/log/supervisor/accounting-queue.log` |
| Cron scheduler runs | (silently to `/dev/null` per crontab) — run `php artisan schedule:list` to inspect schedule |
| PHP-FPM | `/var/log/php8.4-fpm.log` |
| Nginx access/error | `/var/log/nginx/{access,error}.log` |
| Jenkins build output | Jenkins UI → Build → Console Output |
| Audit log (in-app) | `audit_logs` table; viewable at `/audit` in the UI |
| Login attempts | `login_attempts` table; viewable at `/audit/login-attempts` |

**Quick health check:**

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://api.accounting.example.com/sanctum/csrf-cookie
# Expect 204
```

**Common issues:**

| Symptom | Likely cause |
|---|---|
| Login redirects back to `/login` | `SANCTUM_STATEFUL_DOMAINS` or `FRONTEND_URL` mismatch with the actual frontend host |
| 419 Page Expired on login | XSRF cookie not reaching backend — check CORS `supports_credentials`, frontend `credentials: 'include'`, and same-site cookie settings |
| Recurring invoices not generated | Cron not running, or worker not restarted after deploy. Check `recurring_cron.last_run_at` cache key |
| Queue jobs stuck | `sudo supervisorctl restart accounting-queue:*` and inspect `/var/log/supervisor/accounting-queue.log` |
| 500 on first request after deploy | `php artisan config:clear` didn't run, or storage permissions reverted. Re-run `scripts/deploy.sh` manually |

---

## 8. Rollback

The `git filter-repo` history split (2026-04-28) created a `pre-split-be-github` tag pointing at the pre-overwrite `main`. That's a global rollback anchor for the *repository*, not for *deployments*.

For deploy rollback:

### 8.1 Code rollback (via Jenkins)

```bash
# On your laptop
git checkout <last-known-good-sha>
git push --force-with-lease origin main           # or staging
# Wait for Jenkins to redeploy
```

### 8.2 Code rollback (manual, faster for emergencies)

```bash
ssh deploy@<server>
cd /var/www/accounting-backend/production
git fetch
git checkout <last-known-good-sha>
bash scripts/deploy.sh
sudo supervisorctl restart accounting-queue:*
```

(This requires the server-side directory to be a git checkout. If `Jenkinsfile`'s rsync is used directly without git, add a `git init && git remote add origin ...` once during initial setup.)

### 8.3 DB rollback

`migrate --force` is one-way. To undo a migration:

```bash
cd /var/www/accounting-backend/production
php8.4 artisan migrate:rollback --step=1 --force
```

For destructive migrations (column drops, type changes), restore from DB backup instead — there is no automatic rollback.

**Backup before each deploy** (recommended cron, separate from this app):

```bash
# nightly mysqldump example
0 2 * * * mysqldump -u backup -p<pass> accounting_prod | gzip > /backups/accounting/db-$(date +\%F).sql.gz
```

---

## 9. Zero-downtime deploys (if/when needed)

The current pipeline rsyncs in place — there's a brief window during `composer install` and `migrate` when requests can hit half-deployed code. For an internal tool this is acceptable.

If you outgrow this, the standard Laravel pattern is:

1. Deploy each release into a new dated directory: `/var/www/accounting-backend/production-2026-04-28/`.
2. After `composer install` + `migrate --force` succeed, atomically swap a symlink: `ln -nfs production-2026-04-28 current`.
3. Point Nginx at `current/public/`. Reload PHP-FPM (`systemctl reload php8.4-fpm`) so it sees the new symlink target.
4. Restart queue workers.
5. Keep last 3 release dirs for instant rollback (`ln -nfs production-2026-04-21 current`).

This requires rewriting `scripts/deploy.sh` and the `Jenkinsfile` rsync target. Out of scope for the current setup.

---

## See also

- `Jenkinsfile` — pipeline definition (this dir)
- `scripts/deploy.sh` — server-side deploy script
- `docs/superpowers/specs/2026-04-28-monorepo-split-design.md` — repo split history
- `accounting-frontend/docs/deployment.md` — frontend deploy guide
