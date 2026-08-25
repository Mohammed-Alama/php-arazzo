---
name: fix-ci
description: Diagnose and fix failing GitHub Actions CI on a PR or branch. Use when CI fails, checks are red, or the user asks why CI failed or to fix CI.
---

# Fix CI

Turn a red CI run green: fetch the failure, reproduce it locally, fix the root cause, verify.

## Step 1 — Fetch the failure

Run the script:

```bash
bash .agents/skills/fix-ci/scripts/ci-failures.sh
```

It resolves the current branch's PR (or pass `--pr N` / `--branch name` / `--run id`) and prints every failed check plus the tail of each failed step's log. Exit code 1 = failures found.

Re-run it after each push — new runs replace old ones. If checks are still running, wait and re-run rather than diagnosing a partial log.

## Step 2 — Root cause before edits

Read the log excerpts and name one root cause per failed job **before touching any code**. Map each to this repo's local equivalent:

| CI job | Local reproduction |
| --- | --- |
| Code Style (Pint) | `vendor/bin/pint --test --dirty` |
| PHPStan | `composer run analyse` |
| Test P8.4-L* | `composer run test` |

CI runs `composer update`, so a failure that does not reproduce locally may be a dependency-version difference — check for a newly released constraint before blaming your diff.

## Step 3 — Fix minimally

Fix the named root cause; do not refactor along the way. Verify locally with the mapped command until it passes, then re-run the script after pushing.

Completion criterion: `ci-failures.sh` exits 0 — all checks green on the latest commit.
