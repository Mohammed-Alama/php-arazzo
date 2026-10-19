---
name: deps-update
description: Update composer dependencies across root, core, and laravel packages, then re-run gates. Use when bumping dependencies, refreshing locks, or checking for outdated packages.
---

# Update Dependencies

Refreshes all three composer roots (root, `packages/core`, `packages/laravel`) in one deterministic pass, reports lock drift, and re-runs the gates so dependency drift is caught locally instead of by CI's matrix.

## Run

```bash
bash .agents/skills/deps-update/scripts/update-deps.sh [--outdated] [--no-gates]
```

- Updates run `composer update --no-interaction` per root; CI's `test` job does the same, so local parity with the matrix is exact.
- Only the root `composer.lock` is tracked — package locks are gitignored by design; the script still refreshes them.
- Because the root lock *is* tracked and the analysis, issue-intake and package-index jobs install from it, a change to any `composer.json` (root or package) needs `composer update` before it is committed. `composer validate --no-check-publish --check-lock` reports an out-of-date lock locally — but read its output rather than its exit code, which is non-zero even for a valid lock because the manifest's `@dev` constraints produce warnings.
- `--outdated` additionally lists direct-dependency updates per root.
- On gate failure the script exits non-zero and prints the exact revert commands.

## Laravel matrix caveat

CI tests Laravel 12 and 13 via require-hints inside `packages/laravel`; locally only one combination is exercised. If `illuminate/*` or `orchestra/testbench` moved major versions, push and let CI's matrix arbitrate before merging.

Completion criterion: all three updates applied, gates green, root-lock drift reviewed in the diff stat before committing.
