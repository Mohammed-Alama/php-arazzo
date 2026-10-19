# CI: commit the lock, install from it, and keep the float canary

- **Date:** 2026-10-19
- **Status:** proposed
- **Refs:** #232 (the download cache this follows), epic #27, `.github/workflows/ci.yml`,
  `.github/workflows/package-index.yml`, `.github/workflows/issue-intake.yml`

## Intent

Make CI faster by removing the work it does that it does not need to do.

#232 gave every composer site a download cache. Measuring the result showed the
cache is not where the time is: on a **warm** run — cache restored, downloads
free — the PHPStan job's install step still took **45 s of a ~78 s job**, and its
own output says why:

```
Loading composer repositories with package information
Updating dependencies
Lock file operations: 198 installs, 0 updates, 0 removals
```

That is resolution of a floating dependency graph plus extraction of 198
packages. No download cache can touch it: with no committed lock, every job
re-derives the graph from the network and unpacks the whole tree.

## The decision this rests on

The repository deliberately commits **no** `composer.lock` (`.gitignore:2`,
`:41`). CI's `composer update` is therefore a canary — it is how a new upstream
release that breaks this codebase gets noticed.

Removing the 45 s means committing a lock and installing from it, which silences
that canary wherever it is applied. **Decided (human partner, 2026-10-19):** keep
the canary, and pay for speed only in the jobs whose purpose is not dependency
testing.

| Job | Purpose | Uses |
| --- | --- | --- |
| PHPStan, PHP Mess Detector, phpDocumentor | analyse this repository's code | `composer install` |
| package index, issue intake | automation over issues | `composer install` |
| test (`--laravel 13.*`, `--prefer-stable` matrix) | test against a *resolution* | `composer update` (unchanged) |

The test job keeps floating because its matrix **is** about resolution: it pins
`illuminate/contracts` and `orchestra/testbench` per leg with `composer require`
followed by `composer update` (`ci.yml:173-176`). Pinning it to a lock would
empty the matrix of meaning.

## Why a scheduled canary rather than trusting the ones that exist

With the test job still floating, a canary survives — but only on runs a human
causes. Checking whether anything else already covers the gap:

- **Dependabot** does carry a weekly `composer` entry for `/`
  (`.github/dependabot.yml`), but it has produced two pull requests in this
  repository's life and **both were closed, not merged**. It does not advance
  dependency versions here.
- **Nothing scheduled re-resolves.** `package-index.yml` is the only scheduled
  workflow that runs composer, and after this change it *installs* — it stops
  resolving too.

So without a scheduled run, the only fresh resolution left would be the test
job's, which fires when someone happens to push. A weekly `composer update` that
runs the gates turns "we will find out on the next push" into "we will find out
within a week".

## Goals

- The five install jobs stop paying resolution + extraction: a lock hit should
  return their install work to a few seconds.
- CI still notices a breaking upstream release, on a schedule that is stated
  rather than accidental.
- The change is honest about what is cached and what is trusted.

## Non-goals

- Switching the test job to `install`.
- Committing `packages/*/composer.lock` (`.gitignore:41` stays): nothing in CI
  installs them; only the root lock is used.
- Touching the `style` job — it runs the pint action and never invokes composer.
- Replacing the download cache on the test job (#232's, keyed on the manifests).

## Anti-goals — what would make this a failure even if it works

- **CI green with dependencies unverified anywhere.** If the test job also
  stopped floating and the canary were never scheduled, the repository would
  lose the only place that ever sees a fresh resolution.
- **A cache that quietly serves the wrong tree.** A `vendor/` restored against a
  different lock, or a different PHP version, must be a miss, not a silent
  substitution. This is the failure mode with the most subtle fix; see
  *Design § 2*.
- **A lock that rots.** A manifest change without a refreshed lock should be
  loud. Verified (2026-10-19): it is loud when it matters — see the table in
  *Design § 2* — but a satisfiable edit warns rather than fails, so the lock
  still wants a helper. Named in *Left to the builder*.

## Constraints and verified facts

- **Path-repository symlinks are relative** — `vendor/alama/arazzo-cli -> ../../packages/cli/` — so a cached `vendor/` stays valid after a fresh
  checkout at the same absolute path, and across runner images.
- `vendor/` is ~179 MB; the repository cache budget is 10 GB. Five jobs sharing
  one key is ~179 MB; five per-job keys would be ~900 MB for no gain.
- The root lock resolves the whole tree (all `packages/*` via path repositories),
  so one lock covers every install job.
- `actions/cache@v6` accepts multiple paths in one step, and reports
  `cache-hit: false` when only a `restore-keys` prefix matched — which is what
  lets downloads be reused while still running the install.
- The lock currently on this machine is **stale**: `composer validate
  --check-lock` reports that `alama/arazzo-protocol-{http,soap,rpc}` are required
  but absent from it. It must be regenerated before it is committed.
- Verified by experiment, `composer install` against a lock that cannot satisfy
  `composer.json` exits **4**; against one that can, it warns and exits **0**.

## Design

### 1. Commit the root lock

Remove `.gitignore:2` (`composer.lock`). `packages/*/composer.lock` stays
ignored. The lock is a generated artifact; it is committed because it is the
input CI installs from.

Before committing it: run `composer update` once so the committed lock matches
`composer.json`. Committing the stale lock that exists locally today would fail
every install job immediately.

### 2. One combined cache step per install job

Replace #232's download-cache step in the five install jobs with a single step
carrying both directories. The key keeps #232's manifests and adds the lock:

```yaml
      - name: Cache composer downloads and vendor
        id: composer-cache
        uses: actions/cache@v6
        with:
          path: |
            .cache/composer
            vendor
          key: vendor-${{ runner.os }}-php8.4-${{ hashFiles('composer.lock', 'composer.json', 'packages/*/composer.json') }}
          restore-keys: vendor-${{ runner.os }}-php8.4-
```

**Why the manifests stay in the key.** This is the part that is easy to get
wrong. Hashing the lock alone would mean that a pull request editing
`composer.json` without refreshing the lock produces an **exact hit**: the
install is skipped, and nothing ever compares lock to manifest — precisely the
"quietly serves the wrong tree" anti-goal. With the manifests in the key, that
edit is a miss, the install runs, and composer reports the disagreement:

| Manifest change | `composer install` against the committed lock | Verified |
| --- | --- | --- |
| requires a package the lock does not contain | exits **4** — `Required package … is not present in the lock file` | yes |
| narrows a constraint the lock can still satisfy | `Warning: The lock file is not up to date…`, exits **0**, installs from the lock | yes |

So the loud case is loud, and the quiet case is a visible annotation on the pull
request that made the edit — the one place someone can act on it. The residue (a
satisfiable edit that warns once and is then cached) is named in *Left to the
builder*.

**Why the `vendor-` prefix.** The floating test job's key is
`composer-${{ runner.os }}-${{ hashFiles('composer.json', 'packages/*/composer.json') }}`
with `restore-keys: composer-${{ runner.os }}-`. Naming the install jobs' key
`vendor-…` means neither key is a prefix of the other, so the two populations
cannot restore each other's entries by accident, and #232's existing `composer-…`
entries just age out.

**Why the PHP version is a literal in the key.** A `vendor/` tree is only
meaningful for the interpreter it was installed under. Every install job pins
`php-version: '8.4'`, so `php8.4` in the key is correct today, but a version bump
in the workflow must rotate this key by hand. That is a footgun with a note
rather than a mechanism — named here so a reviewer can object.

Two properties follow from the two paths in one step:

- **Exact hit** (lock and manifests unchanged): `vendor/` and the download cache
  come back, `cache-hit == 'true'`, and the install is skipped.
- **Prefix hit** (the lock or a manifest changed): the *previous* snapshot is
  restored, so the download cache still saves fetching the packages that did not
  move, while the install re-runs because `cache-hit` is `false`.

This also removes #232's decay limit for these jobs, and only these: the key now
carries the lock, which describes the *resolved* set. The test job keeps the
manifest-only key, because it has no lock to describe.

### 3. Gate the install, and widen what triggers CI

```yaml
      - name: Install dependencies
        if: steps.composer-cache.outputs.cache-hit != 'true'
        run: composer install --no-progress --prefer-dist --no-interaction
```

`package-index.yml` already installs; `issue-intake.yml` and the three analysis
jobs switch from `composer update` to this same `install`, for the same reason:
none of them is testing a resolution.

`ci.yml`'s `paths` lists (`push` and `pull_request`) carry `composer.json` and
`composer.lock` but no package manifest, so today a change to
`packages/*/composer.json` triggers no CI at all — and after this change it could
leave the root lock stale with nothing to notice. Add
`packages/*/composer.json` to both lists.

### 4. Keep the test job as it is

`composer update` with `--${{ matrix.stability }}`, plus #232's download cache
keyed on the manifests, unchanged.

### 5. A weekly canary

A new `.github/workflows/dependency-canary.yml`, on `schedule` (weekly) and
`workflow_dispatch`: checkout, set up PHP, `composer update`, then run the
repository's own gates (`composer run analyse`, `composer run test`). A fresh
resolution that breaks the codebase makes the run red on the default branch,
which is how the maintainer is told.

## Left to the builder

- Cron day and time, and whether the canary should also file an issue rather
  than merely going red.
- Whether the canary runs the full test suite or the analysis gates alone.
- Whether the pre-commit hook should warn when `composer.json` and
  `composer.lock` disagree — the new failure mode this change introduces for
  contributors and agents, and the residue of the "satisfiable edit warns and is
  then cached" case. Suggested as a follow-up issue, not part of this change.

## Risks

| Risk | Response |
| --- | --- |
| A PR edits a manifest without the lock | Manifests are in the cache key, so the install runs and composer reports it: exit 4 if the lock cannot satisfy the manifest, a warning if it can |
| Cache serves a vendor tree for the wrong PHP version | Key carries the PHP version as a literal; a bump rotates it by hand |
| The five jobs race to save one key on a fill run | Cosmetic: the run is green, the step does not fail, no annotation is produced (verified on #233). A hit never saves |
| The canary is missed because it is off the PR path | It fails on the default branch, which notifies; a red scheduled run is the intended signal |
| The commit lands with the stale local lock | Explicitly regenerated first (§ 1); the install jobs would otherwise fail immediately and visibly |
