# CI Dependency Lock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut the ~45 s every CI job spends resolving and extracting 198 composer packages, by committing the root lock, installing from it behind a `vendor/` cache in the five jobs that are not testing dependency resolution, and moving the float canary to a scheduled run.

**Architecture:** One committed `composer.lock`. Five jobs (`phpstan`, `phpmd`, `phpdoc`, `package-index`, `issue-intake`) replace #232's download-only cache step with a single `actions/cache@v6` step carrying `.cache/composer` and `vendor` under a `vendor-<os>-php8.4-<hash>` key, and run `composer install` only when that step missed. The `test` job is untouched: it still floats, so it still notices a breaking release on every CI run, and a new weekly workflow notices one when nobody pushes.

**Tech Stack:** GitHub Actions, `actions/cache@v6`, `shivammathur/setup-php@v2`, Composer 2, PHP 8.4.

**Spec:** `docs/superpowers/specs/2026-10-19-ci-dependency-lock-design.md`

## Global Constraints

- Work only in `.worktrees/234-ci-lock-install` on branch `chore/234-ci-lock-install`. `main` is never committed to directly.
- PHP is pinned `8.4` in every affected job. `php8.4` is a literal in the cache key and must be rotated by hand with that pin.
- The `test` job in `ci.yml` must not change in any part, including its cache step.
- `.gitignore`'s `/packages/*/composer.lock` line stays; only the root lock becomes tracked.
- Every commit runs the pre-commit hook (generate-docs, pint, `composer analyse`, `check-baselines`, tests) — roughly 5 minutes each. Never `--no-verify`.
- The canary workflow gets no cache: a cold resolution is the point of it.
- The PR body must contain `Closes #234`.

## Review Focus

Inputs and conditions the spec implies but no task's tests exercise, most likely to bite first:

1. **A PR that edits a manifest without refreshing the lock.** Expected: the cache misses (manifests are in the key), the install runs, and composer reports the disagreement. Pinned in Task 2's and Task 3's verification steps, which grep the key for `composer.json`.
2. **A lock change.** Expected: a prefix restore of the *previous* snapshot, `cache-hit` false, install re-runs on warm downloads. Untestable until a lock actually changes; Task 6 records the saved entry, which is the input to that path.
3. **The PHP version literal in the key drifting from the jobs' pinned PHP.** Expected: they move together. Pinned in Task 2's verification step, which counts both.
4. **A cached `vendor/` restored into a fresh checkout.** Expected: relative path-repo symlinks stay valid. Pinned in Task 6's evidence step, which prints the symlink targets from the restored tree's machine.
5. **Five jobs racing to save one key on a fill run.** Expected: two to four `Failed to save: Unable to reserve cache` lines on the first run, green, no annotation (observed on #233). Pinned in Task 6, which reads the fill run's logs.

---

### Task 1: A committed root lock

**Files:**
- Modify: `.gitignore:2` (delete `composer.lock`)
- Create: `composer.lock` (already generated in the worktree by `composer install`)

**Interfaces:**
- Produces: the tracked `composer.lock` that Tasks 2 and 3 hash into their cache keys and install from.

- [ ] **Step 1: Verify the generated lock matches `composer.json`**

Run: `composer validate --no-check-publish --check-lock 2>&1 | grep -c "Lock file errors"`

Expected: `0`. Gate on that grep, **not** the exit code: the command exits non-zero even when the lock is valid, because the manifest's `@dev` constraints produce warnings. (Verified 2026-10-19.)

- [ ] **Step 2: Stop ignoring the root lock**

Delete the `composer.lock` line from `.gitignore` (line 2, under `# Composer Related`). Leave `/packages/*/composer.lock` alone.

- [ ] **Step 3: Verify both halves of the ignore change**

Run: `git check-ignore composer.lock; echo "root exit=$?"; git check-ignore packages/core/composer.lock`

Expected: the first prints nothing and `root exit=1`; the second prints `packages/core/composer.lock` (still ignored).

- [ ] **Step 4: Commit**

```bash
git add .gitignore composer.lock
git commit -m "chore(ci): commit the root composer.lock"
```

Expected: the pre-commit hook runs and passes (~5 min).

- [ ] **Step 5: Verify the commit is the lock plus one line**

Run: `git show --stat HEAD`

Expected: `.gitignore` (1 deletion) and `composer.lock` (~530 KB, 194 packages). Nothing else staged.

---

### Task 2: `ci.yml` — the three analysis jobs install from the lock, and a manifest change triggers CI

**Files:**
- Modify: `.github/workflows/ci.yml` — the PHPStan job (cache step at :71-76, install at :77-78), the PHPMD job (same pair, followed by `- name: Run PHPMD`), the phpDocumentor job (same pair, followed by `- name: Generate API docs (smoke check)`), and both `paths` lists (:6-19 and :20-33)

**Interfaces:**
- Consumes: the tracked `composer.lock` from Task 1.
- Produces: cache step id `composer-cache`, referenced by the install step as `steps.composer-cache.outputs.cache-hit` — the same shape Task 3 copies into two more files.

- [ ] **Step 1: Replace the PHPStan job's cache and install steps**

The three jobs carry byte-identical cache and install steps, so each edit must include the step that follows it to stay unique. For PHPStan:

```yaml
      # Rotate the php8.4 literal in the key when php-version above changes.
      - name: Cache composer downloads and vendor
        id: composer-cache
        uses: actions/cache@v6
        with:
          path: |
            .cache/composer
            vendor
          key: vendor-${{ runner.os }}-php8.4-${{ hashFiles('composer.lock', 'composer.json', 'packages/*/composer.json') }}
          restore-keys: vendor-${{ runner.os }}-php8.4-
      - name: Install composer dependencies
        if: steps.composer-cache.outputs.cache-hit != 'true'
        run: |
          composer install --no-progress --prefer-dist --no-interaction
      - name: Run PHPStan
```

replaces the old cache step, the `composer update` install step, and the `Run PHPStan` line.

- [ ] **Step 2: Repeat for PHPMD and phpDocumentor**

Apply the same three-step replacement (old cache step → combined step, old install → gated `composer install`, trailing anchor line untouched) to the PHPMD job, anchored on `- name: Run PHPMD`, and to the phpDocumentor job, anchored on `- name: Generate API docs (smoke check)`. The replacement text is exactly what Step 1 gives, with that task's anchor as the last line.

- [ ] **Step 3: Add a package manifest to both trigger lists**

In `paths` under both `push` and `pull_request`, add after `- 'composer.lock'`:

```yaml
      - 'packages/*/composer.json'
```

- [ ] **Step 4: Verify the file structurally**

Counts measured against `main` before the edit: 4 `actions/cache@v6` steps, 5 `composer update` calls, 3 `php-version: '8.4'` pins.

Run:

```bash
grep -c "key: vendor-" .github/workflows/ci.yml                      # 3 — the three analysis jobs
grep -c "composer-cache.outputs.cache-hit" .github/workflows/ci.yml  # 3 — each install is gated
grep -c "composer update" .github/workflows/ci.yml                   # 2 — the test job's two, untouched
grep -c "php-version: '8.4'" .github/workflows/ci.yml                # 3 — unchanged pins
grep -c -- "- 'packages/\*/composer.json'" .github/workflows/ci.yml  # 2 — push and pull_request
git diff main -- .github/workflows/ci.yml | grep -c "matrix.stability"  # 0 — the test job is untouched
```

The third and fourth counts reconcile: three jobs pin PHP 8.4 and three carry the `php8.4` key literal, so no pin can drift from its key without one of these counts moving. The `matrix.stability` diff check is the one that proves the floating job was not collateral damage.

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "chore(ci): install from the committed lock in the analysis jobs"
```

---

### Task 3: `package-index.yml` and `issue-intake.yml`

**Files:**
- Modify: `.github/workflows/package-index.yml:56-64` (cache step, then `composer install --no-interaction --prefer-dist`)
- Modify: `.github/workflows/issue-intake.yml:50-56` (cache step, then `composer update --no-progress --prefer-dist --no-interaction`)

**Interfaces:**
- Consumes: the shape Task 2 established (`id: composer-cache`, the `vendor-…` key, the gated install).
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Rewrite both steps in `package-index.yml`**

Same cache step as Task 2 Step 1, same `composer install` line, no trailing anchor needed — it is the only composer job in the file.

- [ ] **Step 2: Rewrite both steps in `issue-intake.yml`**

Same again. This one changes `composer update` to `composer install`: validating an issue is not testing a resolution.

- [ ] **Step 3: Verify both files structurally**

```bash
grep -c "key: vendor-" .github/workflows/package-index.yml .github/workflows/issue-intake.yml           # 1 each
grep -c "composer-cache.outputs.cache-hit" .github/workflows/package-index.yml .github/workflows/issue-intake.yml  # 1 each
grep -rn "composer update" .github/workflows/                                                           # only ci.yml's two, in the test job
```

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/package-index.yml .github/workflows/issue-intake.yml
git commit -m "chore(ci): install from the committed lock in the issue and index jobs"
```

---

### Task 4: The weekly canary

**Files:**
- Create: `.github/workflows/dependency-canary.yml`

**Interfaces:**
- Consumes: nothing from Tasks 1-3.
- Produces: the scheduled run that replaces the float the five jobs gave up.

- [ ] **Step 1: Write the workflow**

```yaml
name: Dependency canary

on:
  schedule:
    # Weekly, Monday 05:30 UTC. Nothing else resolves composer from scratch.
    - cron: '30 5 * * 1'
  workflow_dispatch:

permissions:
  contents: read

jobs:
  canary:
    name: Fresh resolution, then the gates
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - name: Checkout code
        uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, imagick, fileinfo
          coverage: none
      - name: Resolve dependencies from scratch
        run: composer update --no-progress --prefer-dist --no-interaction
      - name: Run the analysis gates
        run: composer run analyse
      - name: Run the test suite
        run: composer run test
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/dependency-canary.yml
git commit -m "chore(ci): resolve dependencies weekly and run the gates"
```

Decided, so a reviewer can object: the canary runs the full suite (not the analysis gates alone) because a breaking release is likelier to show as a failing test than a failing static-analysis run; and it does **not** open an issue — a red scheduled run on the default branch is the notification, and filing one would need `issues: write` and a token in a third place.

- [ ] **Step 3: Verify GitHub accepts the file**

There is no local YAML linter on this machine (`ruby`'s psych is broken, no `pyyaml`, no `yq`, no `actionlint`), so the parser that counts is GitHub's:

```bash
gh workflow run dependency-canary.yml --ref chore/234-ci-lock-install
gh run list --workflow dependency-canary.yml --limit 3
```

Expected: a queued run appears for this branch. A dispatch on a non-default branch is refused if the workflow is invalid, or if GitHub will not accept the ref — in that case leave it to Task 6 Step 6, which dispatches the same file from `main` after the merge.

---

### Task 5: Docs and the skill notes this change makes stale

**Files:**
- Create: `docs/superpowers/plans/2026-10-19-ci-dependency-lock.md` (this file, when committed)
- Create: `docs/superpowers/specs/2026-10-19-ci-dependency-lock-design.md` (already written, uncommitted)
- Modify: `.agents/skills/fix-ci/SKILL.md:32` — "CI runs `composer update`" is now only true of the `test` job
- Modify: `.agents/skills/deps-update/SKILL.md` — its claim that only the root lock is tracked becomes true; add the refresh step a manifest change now requires

**Interfaces:**
- Consumes: the branch's own content.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Add the lock-refresh step to the deps-update skill**

Under the "Run" bullets, add one line stating that a change to any `composer.json` (root or package) needs `composer update` before the commit, because the five install jobs install from the committed root lock and will report the disagreement otherwise, and that `composer validate --no-check-publish --check-lock` checks it locally.

- [ ] **Step 2: Correct the fix-ci skill's dependency line**

Rewrite line 32 so it says the `test` job floats (`composer update`) while the analysis and automation jobs install from the committed lock — so a failure that does not reproduce locally is a dependency-version difference only in the floating job.

- [ ] **Step 3: Commit everything**

```bash
git add docs/superpowers .agents/skills/fix-ci/SKILL.md .agents/skills/deps-update/SKILL.md
git commit -m "docs(ci): record the lock decision and correct the dependency guidance"
```

---

### Task 6: PR, then read the timings off the run

**Files:**
- No repository changes. The deliverable is the merged PR plus its evidence.

**Interfaces:**
- Consumes: all five commits.
- Produces: the measured answer to "did this actually remove the 45 s".

- [ ] **Step 1: Push and open the PR with the closing reference**

```bash
git push -u origin chore/234-ci-lock-install
gh pr create --base main --title "chore(ci): install from a committed lock" \
  --body "Closes #234

<what changed, and the timings below>

The design record is docs/superpowers/specs/2026-10-19-ci-dependency-lock-design.md."
```

- [ ] **Step 2: Capture the fill run before touching it**

Run: `gh run list --branch chore/234-ci-lock-install --limit 5`

Then, for the PHPStan job of that run: `gh run view <run-id> --log > /tmp/fill.log`, and read out (a) the install step's start/end timestamps, (b) whether the cache step reported a save, (c) any `Failed to save` lines from the jobs that lost the race. Old logs are destroyed by a re-run, so capture them first.

- [ ] **Step 3: Re-run and capture the hit**

Run: `gh run rerun <run-id> --failed=false` (or push an empty commit if the re-run is refused), then read the PHPStan job again.

Expected: the cache step reports `Cache restored from key: vendor-Linux-php8.4-…`, `cache-hit` is true, and the install step does not run at all. Record the restore step's duration.

- [ ] **Step 4: Verify the paths that only CI can exercise**

Run: `ls -la vendor/alama/` (the restored tree) and confirm the symlink targets are relative (`../../packages/…`), and `composer validate --no-check-publish --check-lock | grep -c "Lock file errors"` → `0`.

- [ ] **Step 5: Put both timings in the PR body, then merge on green**

Report the before (45 s on a warm run at `e4254e2a`) and after (the install step skipped on a hit, restore time recorded) with the caveat that whole-job duration in this repository is noise-dominated — the step's own numbers are the honest ones. Then merge, delete the remote branch, and remove the worktree.

- [ ] **Step 6: Close the loop**

After the merge:

```bash
gh workflow run dependency-canary.yml --ref main
gh run list --workflow dependency-canary.yml --limit 3
```

A `schedule` never fires on a branch, so this dispatch is the only way to exercise the canary end to end (and the fallback for Task 4 Step 3). Then file the design record's remaining follow-up as its own issue under epic #27 — a pre-commit warning when `composer.json` and `composer.lock` disagree (`kind:chore`, `level:story`) — rather than smuggling it into this PR.
