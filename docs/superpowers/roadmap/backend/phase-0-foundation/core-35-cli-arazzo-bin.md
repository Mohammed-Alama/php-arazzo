# `bin/arazzo` — Standalone CLI

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Depends on: core-extraction (`plans/2026-07-25-plan-a-core-extraction.md`)

## Problem

All operator entry points today are Laravel artisan commands (`arazzo:validate`,
`arazzo:run`, `arazzo:list`). A pure-PHP consumer (Symfony, Drupal, Mezzio, CI pipeline,
Docker sidecar) cannot invoke the engine without a full Laravel install. Blocks core
extraction, blocks conformance testing, blocks bridge development.

## Feature

Ship `bin/arazzo` in `alama/arazzo-core`, built on `symfony/console`:

```
arazzo validate <workflow.yaml>
arazzo run <workflow.yaml> [--input=key=value] [--output=json|yaml]
arazzo list <workflow.yaml>
arazzo lint <workflow.yaml> [--against-openapi=<spec>]
arazzo generate:from-openapi <spec> [--workflow-id=<id>] [-o out.yaml]
arazzo oak:search|show|import <arg>                # (when tenant-33 lands)
```

Uses in-memory reference impls for `QueueDriverInterface` / `LockManagerInterface` /
`EventLedgerInterface` / `HotStateStoreInterface`. PSR-3 stderr logger. PSR-18 HTTP client
via composer autodiscovery (php-http/discovery).

## Acceptance

- Runs against a fresh `composer create-project alama/arazzo-core skeleton` with zero
  framework packages installed.
- All existing artisan command semantics have a `bin/arazzo` equivalent.
- Exit codes: `0` success, `1` validation failure, `2` execution failure, `3` config error.
- `--format=json` machine-readable output for CI wrapping.

## Out of scope

- Interactive TUI (dashboards / prompts) — separate concern.
- Package as PHAR — v2.
