# Monorepo Split — accounting_timedoor → accounting-backend + accounting-frontend

**Date:** 2026-04-28
**Status:** Approved (brainstorm)
**Type:** Repository / infrastructure change (no application code changes)

## Goal

Dismantle the `accounting_timedoor` monorepo into two fully independent sibling repos, with documentation distributed by usage. Backend's commit history (currently tracked in the outer monorepo, not in the standalone `accounting_td_be` repo) is preserved by extraction. Frontend is already canonical in its own repo and is left intact.

## Context

Current state of `~/Documents/Projects/internal/accounting_timedoor/`:

- **Outer repo** `git@github.com:peasant91/accounting_td.git` on `main`. Tracks `backend/` as ordinary files and `frontend/` as a git submodule pointer (mode `160000`) — but with **no `.gitmodules`** (broken submodule). Recent commits (`feat(auth)`, `feat(audit)`, `feat(admin)`, etc.) are the de-facto canonical backend history.
- **Standalone backend repo** at `backend/.git/`. Remotes: `origin = github.com:peasant91/accounting_td_be.git`, `bitbucket = bitbucket.org:timedoor/accounting_td_backend.git`. Local `main` and `origin/main` are at `a92ee7d "add cron job"` — ~4 commits total, **far behind** the outer monorepo's backend tree. `bitbucket/main` has 3 extra CI/CD commits (`b30f1e9`, `987a29c`, `9389a4c`).
- **Standalone frontend repo** at `frontend/.git/`. Remotes: `origin = github.com:peasant91/accounting_td_fe.git`, `bitbucket = bitbucket.org:timedoor/accounting_td_fe.git`. HEAD `4b18fe9` matches the outer monorepo's submodule pointer. Already canonical.
- ~20 modified files in `backend/` working tree — the diff between the standalone backend repo's stale `a92ee7d` and the outer monorepo's current backend tree. Once filtering treats the outer monorepo as the source, this delta resolves automatically (the inner `.git` is discarded).
- Cross-cutting documentation in outer repo: `docs/superpowers/`, `documentation/Recurring`, `.agent/workflows/invoice-template-system.md`, `.kiro/specs/` (8 features), `.kiro/steering/`, `Production Deployment Guide`, `README.md`.

Sole developer is `timedoor.developer@gmail.com` — force-push is safe.

## Non-goals

- Modifying any application code (Laravel or Next.js).
- Reworking CI/CD pipelines beyond moving files. (Path adjustments inside CI files are flagged for the implementation plan but the *pipelines themselves* aren't redesigned here.)
- Splitting the cross-cutting feature specs into per-stack pieces. Each spec lands in one repo only, chosen by dominant usage.
- Converting `frontend/`'s broken submodule into a real submodule. The outer repo is being deleted, so this becomes moot.

## Decisions captured during brainstorming

| # | Decision |
|---|---|
| 1 | End state: fully split, no monorepo. Outer `accounting_td` GitHub repo deleted at the end. |
| 2 | Backend history strategy: filter outer monorepo's `backend/` path → force-push to `accounting_td_be` as canonical history. |
| 3 | Bitbucket-only CI commits: copy CI files into outer monorepo's `backend/` first, commit as one `ci(backend): add deploy + ci-cd setup`, then filter. Original 3-commit granularity not preserved; file content preserved. |
| 4 | Directory layout: `~/Documents/Projects/internal/accounting-backend/` and `~/Documents/Projects/internal/accounting-frontend/`. |
| 5 | Documentation: split between the two new repos by dominant usage (categorization in §Documentation distribution). `.kiro/steering/{product,tech}.md` dropped. |
| 6 | Remotes: keep both `origin` (GitHub) and `bitbucket` on each repo. Push to both. |
| 7 | Outer `accounting_td` GitHub repo deleted after verification. |
| 8 | Per-repo docs folder named `docs/`. |

## Target state

### Directory layout

```
~/Documents/Projects/internal/
├── accounting-backend/                   ← new, standalone Laravel repo
│   ├── .git/                             remotes: origin (github), bitbucket
│   ├── app/, config/, routes/, tests/, ...   (Laravel code)
│   ├── docs/
│   │   ├── superpowers/
│   │   │   ├── specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md
│   │   │   └── plans/2026-04-18-recurring-health-and-multi-currency-receivables.md
│   │   ├── kiro/specs/{tech-stack-update, customer-currency-company,
│   │   │                invoice-payment-proof, recurring-invoice-system, accounting-module}/
│   │   ├── recurring-walkthrough.md      (was documentation/Recurring)
│   │   └── production-deployment.md      (was Production Deployment Guide)
│   └── README.md                         (new, per-repo)
│
├── accounting-frontend/                  ← was accounting_timedoor/frontend/
│   ├── .git/                             remotes: origin (github), bitbucket
│   ├── app/, components/, lib/, ...      (Next.js code)
│   ├── docs/
│   │   ├── superpowers/
│   │   │   ├── specs/2026-04-19-admin-auth-and-audit-log-design.md
│   │   │   └── plans/2026-04-19-admin-auth-and-audit-log.md
│   │   ├── kiro/specs/{ui-migration, invoice-form-builder}/
│   │   └── agent/workflows/invoice-template-system.md
│   └── README.md                         (existing)
│
└── accounting_timedoor/                  ← deleted at end of migration
```

### Repo state at end

| Repo | Local path | Remotes | History source |
|---|---|---|---|
| `accounting_td_be` | `accounting-backend/` | `origin` (GitHub) + `bitbucket` | Filtered from outer monorepo's `backend/` path. All `feat(auth)`, `feat(audit)`, `feat(admin)` history preserved. Force-pushed (overwrites prior 4-commit history). |
| `accounting_td_fe` | `accounting-frontend/` | `origin` (GitHub) + `bitbucket` | Unchanged; gains a single docs commit on top of `4b18fe9`. |
| `accounting_td` (outer) | — | — | Deleted on GitHub after verification. |

### Documentation distribution

**→ `accounting-backend/docs/`** (BE-dominant)
- `docs/superpowers/specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md` — cron, schema, services
- `docs/superpowers/plans/2026-04-18-recurring-health-and-multi-currency-receivables.md`
- `documentation/Recurring` (renamed `recurring-walkthrough.md`)
- `.kiro/specs/tech-stack-update/` — DB / queue / SMTP
- `.kiro/specs/customer-currency-company/` — data fields / API
- `.kiro/specs/invoice-payment-proof/` — file upload + BE storage
- `.kiro/specs/recurring-invoice-system/` — recurring engine
- `.kiro/specs/accounting-module/` — BE-first module
- `Production Deployment Guide` (renamed `production-deployment.md`)
- This very spec: `docs/superpowers/specs/2026-04-28-monorepo-split-design.md` (preserved at the same path inside `accounting-backend/`, since the outer repo is being deleted in Phase 10).

**→ `accounting-frontend/docs/`** (FE-dominant)
- `docs/superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md` — middleware, AuthProvider, Playwright
- `docs/superpowers/plans/2026-04-19-admin-auth-and-audit-log.md`
- `.kiro/specs/ui-migration/` — UI / styling
- `.kiro/specs/invoice-form-builder/` — per-customer template UI
- `.agent/workflows/invoice-template-system.md` — template registry + locale rendering

**Dropped**
- Outer `README.md` (1 line, replaced by per-repo READMEs).
- `.kiro/steering/{product,tech}.md`.
- `.claude/`, `.claire/` (local AI-tooling configs, never tracked).

## Migration sequence

10 phases. Each phase verifiable independently; no phase proceeds if the prior one failed.

### Phase 0 — Backup

1. `git clone --mirror` of all six remotes (3 GitHub + 3 Bitbucket) → `~/backups/accounting-split-YYYYMMDD/`.
2. `tar` of the entire `accounting_timedoor/` working tree (captures uncommitted backend edits + untracked content).
3. Tag outer monorepo's current `main` as `pre-split-snapshot`; push tag to `origin`.
4. Pre-flight: confirm `git filter-repo` is installed (`brew install git-filter-repo` if not).

### Phase 1 — Reconcile uncommitted backend changes

The ~20 modified files in `backend/` are noise from the inner `.git`'s stale view. The outer monorepo's tree is already canonical. **No commit required.** The inner `.git/` is discarded in Phase 4.

Pre-flight check: `cd backend && git log origin/main..HEAD` and `git stash list` must both be empty (no unpushed commits, no stash entries). Fail-loud if not.

### Phase 2 — Stage CI files into outer monorepo

1. From a temporary clone of `accounting_td_be@bitbucket/main`, copy the files that the 3 CI commits introduced into outer monorepo's `backend/`.
2. Commit in outer monorepo: `ci(backend): add deploy + ci-cd setup`.

(Concrete file list determined when running the implementation: typical candidates are `.github/workflows/`, `bitbucket-pipelines.yml`, `Dockerfile`, deploy scripts.)

### Phase 3 — Add backend docs into outer monorepo

In outer monorepo, populate `backend/docs/` per the §Documentation distribution table. File renames:
- `documentation/Recurring` → `backend/docs/recurring-walkthrough.md`
- `Production Deployment Guide` → `backend/docs/production-deployment.md`
- Other files keep their basenames; just relocate paths.

Add per-repo `backend/README.md` (clone instructions, basic dev commands). Commit: `docs(backend): import historical specs and walkthroughs`.

### Phase 4 — Filter outer monorepo to backend-only history

```bash
cp -R accounting_timedoor accounting-backend-staging
cd accounting-backend-staging
rm -rf .git/hooks       # avoid surprises from local hooks
git filter-repo --path backend/ --path-rename backend/:
```

Result: a fresh git history containing only the `backend/` subtree (now at root). Frontend gitlinks, top-level `docs/superpowers/`, `.kiro/`, etc. removed from history. Original commit messages preserved. Original authors preserved (no `--mailmap`).

### Phase 5 — Force-push to `accounting_td_be`

In `accounting-backend-staging/`:

1. Add remotes: `origin = github.com:peasant91/accounting_td_be.git`, `bitbucket = bitbucket.org:timedoor/accounting_td_backend.git`.
2. From the Phase 0 mirror clones, push tags `pre-split-be-github` and `pre-split-be-bitbucket` pointing at each remote's pre-overwrite `main`. Tags survive force-push.
3. `git push --force-with-lease origin main`
4. `git push --force-with-lease bitbucket main`

### Phase 6 — Place backend at final path

```bash
mv accounting-backend-staging ~/Documents/Projects/internal/accounting-backend
cd ~/Documents/Projects/internal/accounting-backend
cp ~/Documents/Projects/internal/accounting_timedoor/backend/.env .env
composer install
```

### Phase 7 — Frontend docs commit

Frontend repo is already standalone at `accounting_timedoor/frontend/`. In place:

1. Add `docs/` subtree per §Documentation distribution.
2. Commit: `docs(frontend): import historical specs and workflow notes`.
3. `git push origin main && git push bitbucket main`.

### Phase 8 — Move frontend to final path

```bash
mv ~/Documents/Projects/internal/accounting_timedoor/frontend \
   ~/Documents/Projects/internal/accounting-frontend
```

(`.env.local` and other untracked files travel with the directory.)

### Phase 9 — Verification gates

All checks must pass before Phase 10. Any failure → rollback.

**Backend (`accounting-backend/`):**
- `git log --oneline | wc -l` ≈ count of outer-monorepo commits that touched `backend/` (sanity check).
- Spot-check recent feat/audit/admin commits exist in log.
- `git remote -v` shows both `origin` and `bitbucket`.
- `git status` clean.
- `composer install` succeeds.
- `php artisan test` passes (same suite as pre-split).
- `php artisan route:list` runs without errors.
- `docs/` subtree contents match §Documentation distribution.

**Frontend (`accounting-frontend/`):**
- `git log -1` shows the new `docs(frontend): …` commit on top of pre-existing `4b18fe9`.
- `git remote -v` unchanged.
- `git status` clean.
- `npm install` no-op; `npm run build` succeeds.
- `npm run e2e` passes (same suite as pre-split).
- `docs/` subtree contents match §Documentation distribution.

**GitHub:**
- `gh repo view peasant91/accounting_td_be` shows recent push; tag `pre-split-be-github` exists.
- `gh repo view peasant91/accounting_td_fe` shows recent push.
- `gh repo view peasant91/accounting_td` still exists (not yet deleted).

**Bitbucket:**
- `accounting_td_backend` shows recent push + tag `pre-split-be-bitbucket`.
- `accounting_td_fe` shows recent push.

### Phase 10 — Decommission outer monorepo

Only after Phase 9 fully passes:
1. `gh repo delete peasant91/accounting_td --yes`.
2. `rm -rf ~/Documents/Projects/internal/accounting_timedoor`.
3. Backups in `~/backups/accounting-split-YYYYMMDD/` retained until manually cleared.

## Safety, rollback, error handling

### Safety guarantees

| Risk | Mitigation |
|---|---|
| Force-push destroys old `accounting_td_be` history | Pre-overwrite tags `pre-split-be-github` / `pre-split-be-bitbucket` pushed to each remote first (Phase 5.2). Plus mirror clones in `~/backups/`. |
| Filter-repo loses commits unexpectedly | Phase 4 operates on a copy (`accounting-backend-staging`), never on the original. Original `accounting_timedoor/` untouched until Phase 10. |
| `.env` files lost | They're gitignored; explicit copy in Phase 6 + `mv` carries them in Phase 8. Plus Phase 0 tarball. |
| Standalone backend had local commits I missed | Phase 0 mirror clones capture all remote refs. Phase 1 fail-loud check on `git log origin/main..HEAD` and `git stash list`. |
| GitHub deletion irreversible | Deferred to Phase 10, only after Phase 9 passes. |
| `git filter-repo` not installed | Phase 0 pre-flight; install via Homebrew. |

### Rollback (before Phase 10)

Original `accounting_timedoor/` is **not modified** during Phases 0–9 (we only copy from it). To roll back:

1. `rm -rf ~/Documents/Projects/internal/accounting-backend ~/Documents/Projects/internal/accounting-frontend ~/Documents/Projects/internal/accounting-backend-staging`.
2. Restore old backend `main` from the pre-split tags. From a fresh clone of either remote:
   - `git fetch origin --tags && git fetch bitbucket --tags`
   - `git push --force origin refs/tags/pre-split-be-github:refs/heads/main`
   - `git push --force bitbucket refs/tags/pre-split-be-bitbucket:refs/heads/main`
3. Frontend rollback: `git reset --hard <prev-HEAD>` + force-push to both remotes (only the docs commit needs reverting).
4. Resume work in `accounting_timedoor/` as before.

### Open caveats / non-goals

- **CI pipelines**: when the deploy/CI files land in `accounting-backend/`, any path-references inside them (e.g., `cd backend && ...`) need updating to repo-root paths. Flagged for the implementation plan; not redesigned here.
- **IDE configs** (`.kiro/`, `.agent/`, `.claude/`, `.claire/`): not preserved as a shared layer. Tracked content (specs, workflow notes) moves into per-repo `docs/`. Runtime configs stay local-only via per-repo `.gitignore`.
- **Self-preservation of this design doc**: handled inline — the spec is listed in §Documentation distribution under the backend bucket, so it travels into `accounting-backend/docs/superpowers/specs/` as part of Phase 3 and survives Phase 10's deletion of the outer repo.
