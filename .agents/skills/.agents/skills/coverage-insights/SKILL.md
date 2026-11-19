---
name: coverage-insights
description: Query Pest PHPUnit coverage (HTML) for human + agent consumption. Use when you need to know "what is covered?", "where are the gaps?", "is this file tested?", or to guide test writing. Parses packages/*/coverage-report/index.html + dashboard.html + per-file HTML. Supports --overview, --file, --hotspots, --uncovered, --dashboard with --json for agents. Generate fresh reports via phpdbg when pcov/xdebug not available.
---
> **Merged** — this skill is now part of `falsification-testing` comprehensive skill. Use `make coverage-query` or `php .agents/skills/falsification-testing/scripts/query-coverage.php`.


# Coverage Insights — Pest HTML

## When to use

- After `make test-coverage` or `vendor/bin/pest --coverage` to answer "what is the coverage?"
- Before writing tests: `make coverage-hotspots` / `make coverage-query ARGS="--file Runner/Execution/StepExecutor.php"` to find gaps
- In CI or by an agent: `--json` returns machine-readable metrics
- When pest says `87.55%` but you need *which* lines are uncovered

## Package context

Reports live (gitignored) at:

```
packages/core/coverage-report/index.html       — total + per-directory
packages/core/coverage-report/dashboard.html   — insufficient coverage + risks
packages/core/coverage-report/<path>.php.html  — per-file line coverage (popin = covered, danger = uncovered)
packages/laravel/coverage-report/...           — same for laravel
```

Generate with (no pcov/xdebug needed — uses phpdbg):

```bash
make coverage              # both packages, html + clover
make coverage-core         # core only
make coverage-laravel      # laravel only
```

Or directly:
```bash
phpdbg -qrr vendor/bin/pest --coverage --coverage-html=coverage-report --coverage-clover=coverage.xml
```

## Scripts

| Script | Purpose | Human | Agent |
|--------|---------|-------|-------|
| `query-coverage.php` | Main query — overview/file/hotspots/uncovered/dashboard | `php query-coverage.php --overview` / `--file X` / `--hotspots` | `... --json` |
| `generate-coverage.sh` | Regenerate reports (phpdbg) | `bash generate-coverage.sh --core` | same, + `--json` summary |

## Quick start

```bash
# Human: overview table
php .agents/skills/coverage-insights/scripts/query-coverage.php --overview
make coverage-query ARGS="--overview"

# Human: hotspots (lowest coverage)
php .agents/skills/coverage-insights/scripts/query-coverage.php --hotspots --limit 10
make coverage-hotspots

# Human: per-file
php .agents/skills/coverage-insights/scripts/query-coverage.php --file Runner/Execution/StepExecutor.php
make coverage-query ARGS="--file Runner/Execution/StepExecutor.php"

# Human: uncovered lines
php .agents/skills/coverage-insights/scripts/query-coverage.php --uncovered --file Runner/Execution/WorkflowEngine.php

# Agent: same with --json
php .agents/skills/coverage-insights/scripts/query-coverage.php --overview --json | jq
php .agents/skills/coverage-insights/scripts/query-coverage.php --file Runner/Execution/StepExecutor.php --json | jq .uncovered
php .agents/skills/coverage-insights/scripts/query-coverage.php --hotspots --limit 5 --json | jq .hotspots
```

## Make targets

| Make | Equivalent |
|------|------------|
| `make coverage` | `generate-coverage.sh --all` |
| `make coverage-core` | `generate-coverage.sh --core` |
| `make coverage-query ARGS="--overview --json"` | `query-coverage.php` |
| `make coverage-hotspots ARGS="--limit 10"` | `query-coverage.php --hotspots` |
| `make coverage-dashboard` | `query-coverage.php --dashboard` |

## Interpreting coverage (with falsification)

`87.55%` line coverage ≠ `87.55%` falsified. Coverage theater is real — see `falsification-testing` skill. Use:

- `make detect-fake` alongside `make coverage` — high coverage + high `hume-audit` survival = decoration
- `make audit-boundaries` to check Hume boundaries not covered by lines
- `make coverage-query --hotspots` to prioritize the least-covered, highest-risk classes (e.g. `WorkflowEngine 32%`)

## Output contract

- Human: tables/lists, `[MISS]`/`[~hit]`, progress bars as `%`
- Agent: `--json` always returns `{"package":..., "total":{...}, "directories":[...], "hotspots":[...], "file":{...}, "uncovered":[...]}` with stable keys, exit `0` on success, `1` if report missing, `2` usage
