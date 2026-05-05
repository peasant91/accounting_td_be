# accounting-backend

Laravel 12 + PHP 8.2 backend for the Timedoor internal accounting application.
Standalone repo extracted from the `accounting_timedoor` monorepo on 2026-04-28
via `git filter-repo` — see `docs/superpowers/specs/2026-04-28-monorepo-split-design.md`.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Or use the all-in-one shortcut (install + key + migrate + npm build):

```bash
composer run setup
```

## Run

The app needs four processes in development:

| Process | Command | Purpose |
| --- | --- | --- |
| HTTP server | `php artisan serve` | API on `http://localhost:8000` |
| Queue worker | `php artisan queue:listen --tries=1 --timeout=0` | Background jobs (e.g. `SendInvoiceEmailJob`) |
| Vite dev server | `npm run dev` | Frontend asset HMR |
| Log tail | `php artisan pail --timeout=0` | Live application logs (optional) |

Run them all at once:

```bash
composer run dev
```

This wraps the four commands above with `concurrently` and tears them down together on Ctrl+C.

### Scheduler (recurring invoices)

`routes/console.php` registers `invoices:process-recurring` to run daily at 00:00
Asia/Makassar. It is **not** included in `composer run dev` — start it manually
when working on recurring-billing features:

```bash
php artisan schedule:work
```

In production this is driven by the system cron (see `docs/production-deployment.md`).

## Test

```bash
php artisan test
```

## Companion

Frontend lives at `https://github.com/peasant91/accounting_td_fe`.

## Docs

- Architecture & feature specs: `docs/superpowers/specs/`, `docs/kiro/specs/`
- Deployment: `docs/production-deployment.md`
- Recurring engine walkthrough: `docs/recurring-walkthrough.md`
- CI/CD: `Jenkinsfile`, `scripts/deploy.sh`
