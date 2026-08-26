# Tia Engine Benchmark

Measured impact of [Pest 5's Tia Engine](https://pestphp.com/docs/tia) (Test
Impact Analysis) on this monorepo's aggregated test suite. Recorded on the
working tree described below; re-run the scenarios yourself with the commands
in [Methodology](#methodology).

## Results

| Scenario | Command | Wall clock (3 runs) | Median |
| --- | --- | --- | --- |
| **Before** — serial per-package run (old workflow) | `composer test` | 7.87s / 8.64s / 7.29s | **7.87s** |
| **Before** — parallel full suite, no Tia | `vendor/bin/pest --parallel --no-tia` | 5.17s / 5.64s / 4.78s | **5.17s** |
| Tia cold start — records the dependency graph | `vendor/bin/pest --parallel --tia --fresh` | 18.37s *(one-time)* | 18.37s |
| **After** — warm replay, zero changes | `vendor/bin/pest --parallel` | 2.11s / 2.06s / 1.84s | **2.06s** |
| After — comment-only edit (e.g. a Pint pass) | same, after appending a comment to a source file | 2.15s / 2.53s | 2.15s |
| After — semantic edit to one class, first replay | same, after adding code to `SyncQueueDriver` | 16.53s / 17.08s | ~17s |
| After — subsequent replays of that state | same | 2.45s / 2.22s | **2.3s** |

### Headline numbers

- **vs the old serial workflow:** 7.87s → 2.06s = **~3.8× faster** in steady state.
- **vs parallel without Tia:** 5.17s → 2.06s = **~2.5× faster** in steady state.
- A comment-only or formatting change triggers **zero re-runs** — all ~900
  tests are replayed from cache because Tia normalizes whitespace and comments
  before hashing.
- The cold-start recording (~18s) is paid once per graph invalidation
  (`composer.lock`, `phpunit.xml`, PHP version change, `--fresh`).

### Honest caveats

1. **The first replay after a wide semantic change can cost more than a plain
   full run** (17s vs 5s here): affected tests re-execute *under the PCOV
   driver* so their coverage data can be cached. The Tia win compounds across
   repeated runs — agent loops, watch mode, iterative fixes — not on the first
   run after every edit.
2. Replayed results are faithful: cached entries store the exact lines and
   branches covered, so `--coverage` and `--min` thresholds behave as if the
   whole suite ran.
3. Keep `--tia` out of CI. Pipelines should always run the full suite
   (`vendor/bin/pest --parallel --no-tia`); only a dedicated baseline job may
   record a shared graph.

## Environment

| | |
| --- | --- |
| Date | 2026-08-26 |
| Machine | Apple M1 Pro, 10 cores |
| PHP | 8.4.23 (Laravel Herd, NTS), PCOV enabled |
| Pest / PHPUnit | 5.1.3 / 13.3.1 (paratest, 10 processes) |
| Suite size | ~900 tests (packages/core + packages/laravel aggregated at repo root) |
| Working tree | `main` @ `1d1d455` plus Tia wiring; suite fully green |

## How Tia is wired in this repo

Tia requires the project root to equal the git root, so it runs from the
monorepo root against the aggregated suite:

- [`phpunit.xml.dist`](../phpunit.xml.dist) defines two test suites
  (`packages/core/tests`, `packages/laravel/tests`) and both `src/` trees as
  coverage sources.
- [`tests/Pest.php`](../tests/Pest.php) loads each package's own
  `tests/Pest.php` (single source of truth for per-package wiring) and enables
  `pest()->tia()->locally()` — Tia is on for every local invocation, skipped
  automatically on CI or with `--ci`.
- Daily driver: **`composer tia`** (= `vendor/bin/pest --parallel`).
- Package-level scripts (`composer test-core`, `composer test-laravel`) stay
  Tia-free by design.

Recovering from a corrupted/stale graph:

```bash
rm -rf "$(./vendor/bin/pest --baseline 2>/dev/null || echo ~/.pest/tia/php-arazzo-*)"
composer tia -- --fresh   # or: vendor/bin/pest --parallel --tia --fresh
```

If Pest crashes at shutdown with `A dependency with the name
[Pest\Plugins\Tia\Contracts\State] cannot be resolved`, the root `tests/`
directory (or `tests/Pest.php`) is missing — Pest's boot dies before the Tia
plugin binds its state, and every subsequent run fails the same way. Restoring
the file (or purging `~/.pest/tia/`) recovers it.

## Methodology

Each scenario was run three times (after one warm-up run where noted); wall
clock captured via `/usr/bin/time -p`. The edit scenarios modify a single
git-clean source file (`packages/core/src/Runner/Execution/SyncQueueDriver.php`,
referenced by 8 test files) and restore it with `git checkout --` afterwards.

```bash
# Before: serial (old workflow)
composer test                                   # x3

# Before: parallel, no Tia
vendor/bin/pest --parallel --no-tia             # warmup + x3

# Tia cold recording
vendor/bin/pest --parallel --tia --fresh        # x1

# After: warm replay
vendor/bin/pest --parallel                      # x3

# After: comment-only edit
echo '// bench' >> packages/core/src/Runner/Execution/SyncQueueDriver.php
vendor/bin/pest --parallel                      # x2
git checkout -- packages/core/src/Runner/Execution/SyncQueueDriver.php

# After: semantic edit
perl -pi -e 's{^(    public array \$dispatched = \[\];\n)}{$1\n    private const string BENCH_PROBE = '"'"'probe'"'"';\n}' \
  packages/core/src/Runner/Execution/SyncQueueDriver.php
vendor/bin/pest --parallel                      # x3 (first = re-run affected)
git checkout -- packages/core/src/Runner/Execution/SyncQueueDriver.php
```

Raw logs: wall times quoted above were captured on the date listed; treat them
as indicative for this machine, not as portable absolutes — re-run the script
on your hardware before quoting numbers elsewhere.
