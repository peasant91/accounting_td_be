# accounting-backend

Laravel 12 + PHP 8.2 backend for the Timedoor internal accounting application.
Standalone repo extracted from the `accounting_timedoor` monorepo on 2026-04-28
via `git filter-repo` — see `docs/superpowers/specs/2026-04-28-monorepo-split-design.md`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Run

```bash
php artisan serve
```

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
