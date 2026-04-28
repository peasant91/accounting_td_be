# Monorepo Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the `accounting_timedoor` monorepo into two standalone sibling repos (`accounting-backend`, `accounting-frontend`), preserving the canonical backend history (currently in the outer monorepo, not in the standalone `accounting_td_be` repo) via `git filter-repo` extraction.

**Architecture:** Backup-first, copy-then-mutate. The outer `accounting_timedoor/` directory is read-only until the final phase. We stage doc reorganization commits inside the outer repo, then `git clone --no-local` into a staging dir, run `git filter-repo --path backend/ --path-rename backend/:` to produce a backend-only history, and force-push to `accounting_td_be` (after pre-overwrite tags are pushed to both remotes for rollback). Frontend is already canonical — we just commit a docs subtree there and rename the directory.

**Tech Stack:** `git`, `git-filter-repo` (install: `brew install git-filter-repo`), `gh` (GitHub CLI), `bash`, `composer`, `npm`, `php`. macOS / zsh.

**Conventions:**
- All paths absolute, rooted at `/Users/kevin/Documents/Projects/internal/`. Steps assume zsh.
- "Outer repo" = `/Users/kevin/Documents/Projects/internal/accounting_timedoor` (the monorepo we're dismantling).
- "BE backups dir" = `~/backups/accounting-split-$(date +%Y%m%d)` — set this in Task 2 and reuse.
- Spec reference: `docs/superpowers/specs/2026-04-28-monorepo-split-design.md` (inside outer repo). Keep open in another window.
- Sole developer; force-push is safe.
- Each task ends with verification, not a unit test (this is migration work). Verification commands have explicit expected output.
- Never use `git add -A` / `git add .`. The outer repo has pre-existing dirty state (`.DS_Store`, untracked `.claude/`, `.claire/`, `backend/test-results/`) that must NOT sweep in.

---

## File Structure

This plan does not produce new application code. It produces:

**Inside outer repo (will be deleted in Task 16):**
- New commit: `ci(backend): add deploy + ci-cd setup` adding `backend/Jenkinsfile` and `backend/scripts/deploy.sh`.
- New commit: `chore: commit pending backend hotfixes` for the 2 trivial outer-repo modifications (or "discard" if user prefers).
- New commit: `docs(backend): import historical specs and walkthroughs` reorganizing docs into `backend/docs/` and adding `backend/README.md`.

**Inside frontend repo (`accounting_timedoor/frontend/.git/`):**
- New commit: `docs(frontend): import historical specs and workflow notes` adding the FE-categorized `docs/` subtree.

**New top-level dirs:**
- `~/Documents/Projects/internal/accounting-backend-staging/` (transient, deleted after rename).
- `~/Documents/Projects/internal/accounting-backend/` (final).
- `~/Documents/Projects/internal/accounting-frontend/` (final, renamed from `accounting_timedoor/frontend`).

**New backups dir:**
- `~/backups/accounting-split-YYYYMMDD/` containing 6 mirror clones + 1 working-tree tarball + this file's contents.

---

## Task 1: Pre-flight tooling check

**Files:** none.

- [ ] **Step 1.1: Check `git-filter-repo` install**

```bash
command -v git-filter-repo || echo "NOT INSTALLED"
```

If output is `NOT INSTALLED`:

```bash
brew install git-filter-repo
```

Re-run the check. Expected: a path like `/opt/homebrew/bin/git-filter-repo`.

- [ ] **Step 1.2: Check other tools**

```bash
for cmd in git gh composer php npm; do
  printf "%-12s " "$cmd"; command -v "$cmd" || echo "MISSING"
done
```

Expected: a path for each. If anything is missing, install it before continuing.

- [ ] **Step 1.3: Verify `gh` is authenticated**

```bash
gh auth status
```

Expected: shows `Logged in to github.com` with the account that owns `peasant91/accounting_td*` repos.

- [ ] **Step 1.4: Verify SSH access to Bitbucket**

```bash
ssh -T git@bitbucket.org 2>&1 | head -2
```

Expected: a "logged in as <user>" line. If it errors, fix SSH access before continuing — Phase 5 needs to push to Bitbucket.

---

## Task 2: Create backups (Phase 0)

**Files:** creates `~/backups/accounting-split-YYYYMMDD/`.

- [ ] **Step 2.1: Set the backup dir variable**

```bash
export SPLIT_BACKUP=~/backups/accounting-split-$(date +%Y%m%d)
mkdir -p "$SPLIT_BACKUP"
echo "Backup dir: $SPLIT_BACKUP"
```

- [ ] **Step 2.2: Mirror clone the 3 GitHub remotes**

```bash
cd "$SPLIT_BACKUP"
git clone --mirror git@github.com:peasant91/accounting_td.git    accounting_td-github.git
git clone --mirror git@github.com:peasant91/accounting_td_be.git accounting_td_be-github.git
git clone --mirror git@github.com:peasant91/accounting_td_fe.git accounting_td_fe-github.git
```

- [ ] **Step 2.3: Mirror clone the 3 Bitbucket remotes**

```bash
cd "$SPLIT_BACKUP"
git clone --mirror git@bitbucket.org:timedoor/accounting_td_backend.git accounting_td_backend-bitbucket.git
git clone --mirror git@bitbucket.org:timedoor/accounting_td_fe.git      accounting_td_fe-bitbucket.git
```

(There's no Bitbucket counterpart to the outer `accounting_td` — only the two stack repos exist on Bitbucket. Skip if any of the above doesn't exist; verify with `ls $SPLIT_BACKUP/`.)

- [ ] **Step 2.4: Tarball the entire outer working tree**

```bash
cd /Users/kevin/Documents/Projects/internal
tar -czf "$SPLIT_BACKUP/accounting_timedoor-worktree.tar.gz" accounting_timedoor
ls -lh "$SPLIT_BACKUP/accounting_timedoor-worktree.tar.gz"
```

Expected: a file in the hundreds-of-MB range (because of `frontend/node_modules` and `backend/vendor`).

- [ ] **Step 2.5: Tag outer monorepo's current main as `pre-split-snapshot`**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git tag pre-split-snapshot main
git push origin pre-split-snapshot
```

Expected: `* [new tag]         pre-split-snapshot -> pre-split-snapshot`.

- [ ] **Step 2.6: Verify backups**

```bash
ls -1 "$SPLIT_BACKUP"
```

Expected: 5 mirror clones + 1 tarball. Confirm visually.

---

## Task 3: Pre-flight verify standalone backend repo state

**Files:** read-only inspection of `/Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/.git/`.

- [ ] **Step 3.1: Check no unpushed local commits**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend
git fetch --all 2>&1 | tail -5
git log origin/main..HEAD --oneline
```

Expected: empty output (no commits ahead of github origin).

- [ ] **Step 3.2: Check no stash entries**

```bash
git stash list
```

Expected: empty output.

- [ ] **Step 3.3: Confirm bitbucket has the expected 3 CI commits ahead of HEAD**

```bash
git log HEAD..bitbucket/main --oneline
```

Expected exactly:

```
9389a4c ci(main): update php version to 8.4
987a29c ci(masin): create deploy script and adjust directory deployment
b30f1e9 ci(main): setup ci-cd project
```

If any commit is unexpected, STOP and reconcile manually. The plan assumes only these 3 CI commits exist on `bitbucket/main` beyond `origin/main`.

---

## Task 4: Stage CI files into outer monorepo (Phase 2)

**Files:**
- Create: `/Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/Jenkinsfile`
- Create: `/Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/scripts/deploy.sh`

The 3 bitbucket commits net out to 2 final files (`Jenkinsfile`, `scripts/deploy.sh`) — see spec §Phase 2. We extract the **final state** of these files at `bitbucket/main` and commit as one commit in outer.

- [ ] **Step 4.1: Extract final-state CI files from bitbucket main**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend
git show bitbucket/main:Jenkinsfile      > /tmp/Jenkinsfile.from-bitbucket
git show bitbucket/main:scripts/deploy.sh > /tmp/deploy.sh.from-bitbucket
wc -l /tmp/Jenkinsfile.from-bitbucket /tmp/deploy.sh.from-bitbucket
```

Expected: ~80 lines for `Jenkinsfile`, ~17 lines for `deploy.sh`.

- [ ] **Step 4.2: Place the files in outer repo's `backend/`**

```bash
cp /tmp/Jenkinsfile.from-bitbucket /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/Jenkinsfile
mkdir -p /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/scripts
cp /tmp/deploy.sh.from-bitbucket /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/scripts/deploy.sh
chmod +x /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/scripts/deploy.sh
```

- [ ] **Step 4.3: Stage and commit in outer repo**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git add backend/Jenkinsfile backend/scripts/deploy.sh
git status --short | grep -E "Jenkinsfile|deploy\.sh"
```

Expected:

```
A  backend/Jenkinsfile
A  backend/scripts/deploy.sh
```

(The leading whitespace in `git status` may differ; the key is that exactly these two files are staged as adds.)

```bash
git commit -m "$(cat <<'EOF'
ci(backend): add deploy + ci-cd setup

Brings the Jenkinsfile and scripts/deploy.sh from the standalone
accounting_td_be bitbucket repo into the outer monorepo so the upcoming
filter-repo extraction (see docs/superpowers/specs/2026-04-28-monorepo-split-design.md)
captures them as part of the canonical backend history.

Original commits flattened: b30f1e9, 987a29c, 9389a4c.
EOF
)"
```

- [ ] **Step 4.4: Verify**

```bash
git log -1 --stat
```

Expected: shows the new commit with exactly 2 files added (`backend/Jenkinsfile`, `backend/scripts/deploy.sh`).

---

## Task 5: Resolve outer monorepo's pending changes

**Files:**
- Modify (already-modified): `backend/app/Models/RecurringInvoice.php`, `backend/routes/console.php`.
- Resolve: `frontend` submodule pointer drift.

- [ ] **Step 5.1: Inspect the 2 backend modifications**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git diff backend/app/Models/RecurringInvoice.php backend/routes/console.php
```

Expected: small (1-line each) diffs — the spec recorded these as trivial.

- [ ] **Step 5.2: Decide: commit or revert**

If the diffs look intentional / part of in-progress work → commit them:

```bash
git add backend/app/Models/RecurringInvoice.php backend/routes/console.php
git commit -m "chore(backend): commit pending hotfixes before split"
```

If the diffs are accidental local edits → revert them:

```bash
git checkout -- backend/app/Models/RecurringInvoice.php backend/routes/console.php
```

(If unsure, ASK before proceeding. Default = commit, since they were sitting in the working tree.)

- [ ] **Step 5.3: Resolve frontend submodule pointer drift**

```bash
git status --short | grep "^.M frontend$" || echo "no drift"
```

If drift exists, the outer's tracked pointer is `4b18fe96…` but the submodule HEAD is `4b18fe97…`. Since the outer repo is being deleted in Task 16, this drift is irrelevant — but `git filter-repo` will refuse to run with dirty state. Resolve by checking out the tracked pointer locally (no commit needed):

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor/frontend
git checkout 4b18fe96d2cb1c1f96eb8bfcb53bcabf3a0e2090
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git status --short | grep "^.M frontend$" && echo "STILL DRIFTED" || echo "drift resolved"
```

(We restore frontend to its real HEAD `4b18fe97...` in Task 11 before the docs commit.)

- [ ] **Step 5.4: Clean up untracked files that should not enter history**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git status --short
```

Acceptable remaining state:
- `?? .claire/`, `?? .claude/` — local AI configs, NOT committed
- `?? backend/test-results/` — playwright/test output, NOT committed
- `?? "Production Deployment Guide"` — file at outer root that becomes part of Task 6
- `M  .DS_Store` — already-tracked but mac noise; leave alone

If anything else appears as `??` or `M`, investigate. Don't proceed with a dirty tree of unknown content.

---

## Task 6: Add backend docs into outer monorepo (Phase 3)

**Files:**
- Create: `backend/docs/` subtree (new dir).
- Move: 9 doc paths from outer root → `backend/docs/`.
- Create: `backend/README.md`.

- [ ] **Step 6.1: Create the docs skeleton**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
mkdir -p backend/docs/superpowers/specs
mkdir -p backend/docs/superpowers/plans
mkdir -p backend/docs/kiro/specs
```

- [ ] **Step 6.2: Move backend-categorized superpowers specs and plans**

```bash
git mv docs/superpowers/specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md \
       backend/docs/superpowers/specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md

git mv docs/superpowers/plans/2026-04-18-recurring-health-and-multi-currency-receivables.md \
       backend/docs/superpowers/plans/2026-04-18-recurring-health-and-multi-currency-receivables.md
```

- [ ] **Step 6.3: Move this very design doc + plan (so they survive Phase 10)**

```bash
git mv docs/superpowers/specs/2026-04-28-monorepo-split-design.md \
       backend/docs/superpowers/specs/2026-04-28-monorepo-split-design.md

git mv docs/superpowers/plans/2026-04-28-monorepo-split.md \
       backend/docs/superpowers/plans/2026-04-28-monorepo-split.md
```

- [ ] **Step 6.4: Move backend-categorized Kiro specs**

```bash
for spec in tech-stack-update customer-currency-company invoice-payment-proof recurring-invoice-system accounting-module; do
  git mv ".kiro/specs/$spec" "backend/docs/kiro/specs/$spec"
done
```

- [ ] **Step 6.5: Move + rename `documentation/Recurring`**

```bash
git mv documentation/Recurring backend/docs/recurring-walkthrough.md
```

- [ ] **Step 6.6: Move + rename `Production Deployment Guide` (currently untracked)**

The deployment guide is currently untracked at outer root. Add it directly under its target name:

```bash
mv "Production Deployment Guide" backend/docs/production-deployment.md
git add backend/docs/production-deployment.md
```

- [ ] **Step 6.7: Write `backend/README.md`**

Replace `backend/README.md` (Laravel default) with one tailored to the standalone repo:

```bash
cat > backend/README.md <<'EOF'
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
EOF
git add backend/README.md
```

- [ ] **Step 6.8: Verify staging looks correct**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git status --short | grep -E "^(R|A|D )" | head -30
```

Expected: a list of `R  docs/... -> backend/docs/...` rename entries plus 2 `A` entries (`production-deployment.md`, `README.md`). No surprise `M`/`A` entries.

```bash
ls backend/docs/
ls backend/docs/superpowers/specs/
ls backend/docs/kiro/specs/
```

Expected: the dir tree from spec §Documentation distribution.

- [ ] **Step 6.9: Commit**

```bash
git commit -m "$(cat <<'EOF'
docs(backend): import historical specs and walkthroughs

Reorganizes outer-monorepo cross-cutting docs into backend/docs/ ahead of
the filter-repo split. Backend-categorized specs (per-spec rationale in
docs/superpowers/specs/2026-04-28-monorepo-split-design.md §Documentation
distribution):

- superpowers/{specs,plans}/2026-04-18-recurring-health…
- superpowers/{specs,plans}/2026-04-28-monorepo-split…
- kiro/specs/{tech-stack-update, customer-currency-company,
              invoice-payment-proof, recurring-invoice-system, accounting-module}
- recurring-walkthrough.md (was documentation/Recurring)
- production-deployment.md (was Production Deployment Guide)

Also replaces backend/README.md with one tailored to the standalone repo.
EOF
)"
```

- [ ] **Step 6.10: Verify final state**

```bash
git log -1 --stat | head -30
```

Expected: large diff with mostly renames, plus 2 new files (`production-deployment.md`, `README.md`). No file deletions outside of the renames.

---

## Task 7: Add frontend docs commit (Phase 7)

**Files:**
- Create inside `accounting_timedoor/frontend/`: `docs/` subtree.
- Source files copied from outer repo's working tree (the FE-categorized docs are still in the outer repo's history but were *moved* in Task 6 — for backend ones. The FE-categorized docs were NOT moved in Task 6 and remain at outer root, available for copy.)

Wait — re-check: Task 6 moves only BE-categorized docs. FE-categorized docs (`docs/superpowers/specs/2026-04-19-...`, `.kiro/specs/{ui-migration,invoice-form-builder}/`, `.agent/workflows/...`) are still in their original outer-root locations after Task 6.

- [ ] **Step 7.1: Restore frontend to its real HEAD**

In Task 5.3 we reverted `frontend/` to the outer's tracked pointer (`4b18fe96…`). Restore the actual main HEAD:

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor/frontend
git checkout main
git rev-parse HEAD
```

Expected: `4b18fe97d2f6ce79a926208e1c2b4c64fe999186`.

- [ ] **Step 7.2: Create the docs skeleton inside frontend repo**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor/frontend
mkdir -p docs/superpowers/specs
mkdir -p docs/superpowers/plans
mkdir -p docs/kiro/specs
mkdir -p docs/agent/workflows
```

- [ ] **Step 7.3: Copy FE-categorized files from outer working tree into frontend repo**

(`cp` not `git mv`, because we're crossing repo boundaries — outer's git won't track moves into frontend's git.)

```bash
OUTER=/Users/kevin/Documents/Projects/internal/accounting_timedoor
cp "$OUTER/docs/superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md" \
   docs/superpowers/specs/
cp "$OUTER/docs/superpowers/plans/2026-04-19-admin-auth-and-audit-log.md" \
   docs/superpowers/plans/
cp -R "$OUTER/.kiro/specs/ui-migration"        docs/kiro/specs/
cp -R "$OUTER/.kiro/specs/invoice-form-builder" docs/kiro/specs/
cp "$OUTER/.agent/workflows/invoice-template-system.md" docs/agent/workflows/
```

- [ ] **Step 7.4: Verify the FE docs tree**

```bash
find docs -type f | sort
```

Expected output (exactly):

```
docs/agent/workflows/invoice-template-system.md
docs/kiro/specs/invoice-form-builder/design.md
docs/kiro/specs/invoice-form-builder/requirements.md
docs/kiro/specs/invoice-form-builder/tasks.md
docs/kiro/specs/ui-migration/design.md
docs/kiro/specs/ui-migration/requirements.md
docs/kiro/specs/ui-migration/tasks.md
docs/superpowers/plans/2026-04-19-admin-auth-and-audit-log.md
docs/superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md
```

- [ ] **Step 7.5: Stage explicit paths and commit**

```bash
git add docs/
git status --short | head -20
```

Expected: only `A  docs/...` entries. No surprise modifications elsewhere.

```bash
git commit -m "$(cat <<'EOF'
docs(frontend): import historical specs and workflow notes

Imports frontend-categorized cross-cutting docs from the
accounting_timedoor monorepo ahead of its dismantling. Per-doc
rationale: docs/superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md
and accounting-backend/docs/superpowers/specs/2026-04-28-monorepo-split-design.md
§Documentation distribution.

- superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md
- superpowers/plans/2026-04-19-admin-auth-and-audit-log.md
- kiro/specs/ui-migration/{requirements,design,tasks}.md
- kiro/specs/invoice-form-builder/{requirements,design,tasks}.md
- agent/workflows/invoice-template-system.md
EOF
)"
```

- [ ] **Step 7.6: Push to origin (github)**

```bash
git push origin main
```

Expected: one commit pushed.

(Note: the dangling `bitbucket` remote was dropped pre-Task-7 because `bitbucket.org:timedoor/accounting_td_fe.git` does not exist. Frontend pushes only to GitHub.)

---

## Task 8: Filter outer monorepo to backend-only history (Phase 4)

**Files:**
- Create: `~/Documents/Projects/internal/accounting-backend-staging/`.

- [ ] **Step 8.1: Clean clone the outer repo to the staging dir**

`git filter-repo` requires a fresh clone. Use `--no-local --no-hardlinks` to avoid hardlink optimization that would tie the staging repo back to the outer's `.git/`.

```bash
cd /Users/kevin/Documents/Projects/internal
git clone --no-local --no-hardlinks accounting_timedoor accounting-backend-staging
cd accounting-backend-staging
git log --oneline -5
```

Expected: shows the latest commits from outer including the new `docs(backend):` and `ci(backend):` commits from Tasks 4 & 6.

- [ ] **Step 8.2: Run filter-repo**

```bash
git filter-repo --path backend/ --path-rename backend/:
```

Expected output ends with something like `Completed successfully.` and a parsed-commit count.

- [ ] **Step 8.3: Verify the post-filter tree**

```bash
ls
```

Expected (root of `accounting-backend-staging` now mirrors what was inside `backend/`):

```
Jenkinsfile  README.md  app  artisan  bootstrap  composer.json  composer.lock
config  database  docs  package.json  phpunit.xml  public  resources
routes  scripts  storage  tests  vendor  vite.config.js
```

(No `frontend/`, no `.kiro/`, no `documentation/`. Plus `Jenkinsfile`, `scripts/`, and `docs/` from Tasks 4 & 6.)

- [ ] **Step 8.4: Verify history was preserved**

```bash
git log --oneline | head -20
```

Expected: rich list of `feat(auth)`, `feat(audit)`, `feat(admin)` commits and the new `ci(backend)` and `docs(backend)` commits.

```bash
git log --oneline | wc -l
```

Note the count for Task 12 verification.

- [ ] **Step 8.5: Verify no orphan refs to deleted paths**

```bash
git log --all --oneline -- frontend/ | head -3
git log --all --oneline -- .kiro/ | head -3
```

Expected: both empty (filter-repo cleaned them).

---

## Task 9: Push pre-overwrite tags to remotes (Phase 5.1–5.2)

**Files:** none (operates on the backups dir from Task 2).

Before force-pushing the new history, mark the existing `accounting_td_be` `main` on each remote with a tag. The tag survives force-push and is the rollback anchor.

- [ ] **Step 9.1: Tag and push pre-split-be-github**

```bash
cd "$SPLIT_BACKUP/accounting_td_be-github.git"
git tag pre-split-be-github main
git push origin pre-split-be-github
```

Expected: `* [new tag]         pre-split-be-github -> pre-split-be-github`.

- [ ] **Step 9.2: Tag and push pre-split-be-bitbucket**

```bash
cd "$SPLIT_BACKUP/accounting_td_backend-bitbucket.git"
git tag pre-split-be-bitbucket main
git push origin pre-split-be-bitbucket
```

Expected: `* [new tag]         pre-split-be-bitbucket -> pre-split-be-bitbucket`.

- [ ] **Step 9.3: Verify tags landed**

```bash
gh api repos/peasant91/accounting_td_be/git/refs/tags/pre-split-be-github | grep -E '"sha"|"ref"' | head -3
```

Expected: a JSON snippet showing the tag exists with a SHA matching `accounting_td_be`'s old `main`.

```bash
git ls-remote git@bitbucket.org:timedoor/accounting_td_backend.git refs/tags/pre-split-be-bitbucket
```

Expected: one line with the SHA.

---

## Task 10: Force-push filtered backend (Phase 5.3–5.4)

**Files:** none (push from `accounting-backend-staging`).

- [ ] **Step 10.1: Add remotes**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting-backend-staging
# filter-repo strips remotes; add them back fresh.
git remote add origin    git@github.com:peasant91/accounting_td_be.git
git remote add bitbucket git@bitbucket.org:timedoor/accounting_td_backend.git
git remote -v
```

Expected: 4 lines (origin fetch+push, bitbucket fetch+push).

- [ ] **Step 10.2: Force-push to GitHub**

```bash
git fetch origin
git push --force-with-lease=main:$(git rev-parse origin/main) origin main
```

(The `--force-with-lease=main:<sha>` form pins it to the exact pre-overwrite SHA, so the push fails if the remote moved unexpectedly.)

Expected: `+ <oldsha>...<newsha> main -> main (forced update)`.

- [ ] **Step 10.3: Force-push to Bitbucket**

```bash
git fetch bitbucket
git push --force-with-lease=main:$(git rev-parse bitbucket/main) bitbucket main
```

Expected: forced-update line.

- [ ] **Step 10.4: Verify tags from Task 9 still exist post-force-push**

```bash
git ls-remote origin    refs/tags/pre-split-be-github
git ls-remote bitbucket refs/tags/pre-split-be-bitbucket
```

Expected: each prints one line with a SHA. (Force-pushing `main` does not delete tags.)

---

## Task 11: Place backend at final path (Phase 6)

**Files:**
- Rename: `accounting-backend-staging/` → `accounting-backend/`.
- Create: `accounting-backend/.env` (copied from outer's `backend/.env`).

- [ ] **Step 11.1: Rename staging to final**

```bash
cd /Users/kevin/Documents/Projects/internal
mv accounting-backend-staging accounting-backend
```

- [ ] **Step 11.2: Restore local `.env`**

```bash
cp /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/.env \
   /Users/kevin/Documents/Projects/internal/accounting-backend/.env
ls -la /Users/kevin/Documents/Projects/internal/accounting-backend/.env
```

Expected: file exists, ~1.3 KB based on outer's `.env`.

- [ ] **Step 11.3: Reinstall composer deps fresh**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting-backend
rm -rf vendor
composer install 2>&1 | tail -5
```

Expected: `Generating optimized autoload files` and no errors.

---

## Task 12: Verify backend repo (Phase 9 backend gates)

**Files:** none (verification only).

- [ ] **Step 12.1: History sanity check**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting-backend
git log --oneline | head -10
```

Expected: latest commits are `docs(backend): import historical specs and walkthroughs`, optional `chore(backend): commit pending hotfixes…` (if Step 5.2 chose commit), and `ci(backend): add deploy + ci-cd setup`.

- [ ] **Step 12.2: Spot-check feature commits exist**

```bash
git log --oneline | grep -E "feat\(auth\)|feat\(audit\)|feat\(admin\)" | head -10
```

Expected: at least 5–10 matching commits. If empty, the filter dropped them — STOP and roll back.

- [ ] **Step 12.3: Remotes**

```bash
git remote -v
```

Expected: both `origin` (github) and `bitbucket`.

- [ ] **Step 12.4: Status clean**

```bash
git status
```

Expected: `nothing to commit, working tree clean`. (`vendor/` is gitignored.)

- [ ] **Step 12.5: Composer & artisan smoke**

```bash
php artisan --version
php artisan route:list 2>&1 | tail -5
```

Expected: Laravel version line; route list completes without exception.

- [ ] **Step 12.6: Run the test suite**

```bash
php artisan test 2>&1 | tail -20
```

Expected: same pass/fail profile as in the original outer monorepo's `backend/`. If any test that previously passed now fails, STOP and investigate before Task 16.

- [ ] **Step 12.7: Verify docs subtree**

```bash
find docs -type f | sort
```

Expected — exactly these:

```
docs/kiro/specs/accounting-module/design.md
docs/kiro/specs/accounting-module/requirements.md
docs/kiro/specs/accounting-module/tasks.md
docs/kiro/specs/customer-currency-company/design.md
docs/kiro/specs/customer-currency-company/requirements.md
docs/kiro/specs/customer-currency-company/tasks.md
docs/kiro/specs/invoice-payment-proof/design.md
docs/kiro/specs/invoice-payment-proof/requirements.md
docs/kiro/specs/invoice-payment-proof/tasks.md
docs/kiro/specs/recurring-invoice-system/design.md
docs/kiro/specs/recurring-invoice-system/requirements.md
docs/kiro/specs/recurring-invoice-system/tasks.md
docs/kiro/specs/tech-stack-update/design.md
docs/kiro/specs/tech-stack-update/requirements.md
docs/kiro/specs/tech-stack-update/tasks.md
docs/production-deployment.md
docs/recurring-walkthrough.md
docs/superpowers/plans/2026-04-18-recurring-health-and-multi-currency-receivables.md
docs/superpowers/plans/2026-04-28-monorepo-split.md
docs/superpowers/specs/2026-04-18-recurring-health-and-multi-currency-receivables-design.md
docs/superpowers/specs/2026-04-28-monorepo-split-design.md
```

- [ ] **Step 12.8: GitHub-side verification**

```bash
gh repo view peasant91/accounting_td_be --json pushedAt,defaultBranchRef -q '.pushedAt,.defaultBranchRef.name'
gh api repos/peasant91/accounting_td_be/git/refs/tags/pre-split-be-github -q '.object.sha' | head -c 40 && echo
```

Expected: `pushedAt` is recent (today); branch name `main`; pre-split-be-github tag SHA matches the old `main` SHA from your Task 2 mirror clone (cross-check via `git -C "$SPLIT_BACKUP/accounting_td_be-github.git" rev-parse main`).

---

## Task 13: Move frontend to final path (Phase 8)

**Files:**
- Rename: `accounting_timedoor/frontend/` → `accounting-frontend/`.

- [ ] **Step 13.1: Move the directory**

```bash
mv /Users/kevin/Documents/Projects/internal/accounting_timedoor/frontend \
   /Users/kevin/Documents/Projects/internal/accounting-frontend
```

`mv` of a directory carries everything inside, including `.git/`, `.env.local`, `node_modules/`, `.next/`, `test-results/`. No reinstall needed.

- [ ] **Step 13.2: Verify**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting-frontend
git remote -v
git status
git log --oneline -3
ls .env.local 2>&1
```

Expected:
- One remote: origin (github). (The bitbucket remote was dropped pre-Task-7 because the FE bitbucket repo doesn't exist.)
- Status clean (might show `?? test-results/` if previously untracked — that's fine).
- Latest commit is `docs(frontend): import historical specs and workflow notes`, then `4b18fe9 test(e2e): fix selectors and timing`.
- `.env.local` exists.

---

## Task 14: Verify frontend repo (Phase 9 frontend gates)

**Files:** none (verification only).

- [ ] **Step 14.1: Build smoke**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting-frontend
npm run build 2>&1 | tail -10
```

Expected: `✓ Compiled successfully` (or equivalent for the configured Next version).

- [ ] **Step 14.2: E2E smoke**

```bash
npm run e2e 2>&1 | tail -10
```

Expected: same pass profile as before the split.

If the frontend project relies on the backend running (it does — proxy.ts), start the backend first:

```bash
# in another terminal
cd /Users/kevin/Documents/Projects/internal/accounting-backend
php artisan serve
```

- [ ] **Step 14.3: Verify docs subtree**

```bash
find docs -type f | sort
```

Expected — exactly:

```
docs/agent/workflows/invoice-template-system.md
docs/kiro/specs/invoice-form-builder/design.md
docs/kiro/specs/invoice-form-builder/requirements.md
docs/kiro/specs/invoice-form-builder/tasks.md
docs/kiro/specs/ui-migration/design.md
docs/kiro/specs/ui-migration/requirements.md
docs/kiro/specs/ui-migration/tasks.md
docs/superpowers/plans/2026-04-19-admin-auth-and-audit-log.md
docs/superpowers/specs/2026-04-19-admin-auth-and-audit-log-design.md
```

- [ ] **Step 14.4: Remote verification**

```bash
gh repo view peasant91/accounting_td_fe --json pushedAt -q '.pushedAt'
git remote -v | grep -v bitbucket
```

Expected: `pushedAt` is recent (today). Only `origin` (github) listed; no `bitbucket` remote (dropped pre-Task-7 because the FE bitbucket repo doesn't exist).

---

## Task 15: Final dual-repo sanity (cross-checks)

**Files:** none (verification only).

- [ ] **Step 15.1: Confirm outer monorepo is untouched and still functional**

```bash
cd /Users/kevin/Documents/Projects/internal/accounting_timedoor
git log --oneline -3
git status --short
ls
```

Expected: outer repo still has its full history (now including the new `ci(backend):` and `docs(backend):` commits from Tasks 4 & 6). Working tree shows the partial state where docs were moved into `backend/docs/`. The original `frontend/` symlink/dir has been removed by Task 13.

This confirms we have a working rollback point until Task 16 finalizes.

- [ ] **Step 15.2: Confirm two new repos exist at expected paths**

```bash
ls -d /Users/kevin/Documents/Projects/internal/accounting-{backend,frontend}
```

Expected: both directories listed.

- [ ] **Step 15.3: Sanity diff of code content (backend)**

```bash
diff -rq /Users/kevin/Documents/Projects/internal/accounting_timedoor/backend/app \
         /Users/kevin/Documents/Projects/internal/accounting-backend/app | head -10
```

Expected: empty output (no differences). If files differ, investigate before Task 16.

- [ ] **Step 15.4: Pause for human review**

STOP here. The user (Kevin) reviews:
- Both new repos open correctly in their editor.
- `docs/` looks reasonable in each.
- `php artisan test` (backend) and `npm run build` + `npm run e2e` (frontend) all pass.
- Bitbucket / GitHub UIs show the new pushes.

Only after explicit user confirmation, proceed to Task 16.

---

## Task 16: Decommission outer monorepo (Phase 10)

**Files:**
- Delete: outer GitHub repo `peasant91/accounting_td`.
- Delete: local `~/Documents/Projects/internal/accounting_timedoor/`.

- [ ] **Step 16.1: Final pre-deletion confirm**

```bash
ls "$SPLIT_BACKUP"
ls -d /Users/kevin/Documents/Projects/internal/accounting-{backend,frontend}
```

Both must succeed. The backups dir must contain the mirror clone of `accounting_td-github.git` so the outer repo is recoverable post-deletion.

- [ ] **Step 16.2: Delete the outer GitHub repo**

```bash
gh repo delete peasant91/accounting_td --yes
```

Expected: `✓ Deleted repository peasant91/accounting_td`.

(If `gh` prompts for the repo name, type `peasant91/accounting_td`.)

- [ ] **Step 16.3: Delete the local outer monorepo dir**

```bash
cd /Users/kevin/Documents/Projects/internal
rm -rf accounting_timedoor
ls -d accounting_timedoor 2>&1
```

Expected: `ls: accounting_timedoor: No such file or directory`.

- [ ] **Step 16.4: Final verification**

```bash
ls -1 /Users/kevin/Documents/Projects/internal/ | grep accounting
```

Expected exactly:

```
accounting-backend
accounting-frontend
```

(plus any other unrelated `accounting*` dirs that pre-existed; review the list to confirm `accounting_timedoor` is gone).

- [ ] **Step 16.5: Confirm backups still intact**

```bash
ls -lh "$SPLIT_BACKUP"
```

Expected: 5+ mirror clones + 1 tarball, sizes unchanged from Task 2. **Do not delete this dir.** Retain indefinitely; the user manually clears when satisfied.

---

## Done

Two standalone repos at `~/Documents/Projects/internal/accounting-{backend,frontend}`. Outer monorepo removed locally and on GitHub. Pre-split rollback tags exist on both `accounting_td_be` remotes. Full mirror backups in `$SPLIT_BACKUP`.

If anything later turns out wrong, the rollback procedure is documented in `accounting-backend/docs/superpowers/specs/2026-04-28-monorepo-split-design.md` §Rollback.
