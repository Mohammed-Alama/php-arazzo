# Package Release + Rename to php-arazzo/arazzo-*

## Intent and why

Prepare `alama/php-arazzo` for public release under the `php-arazzo` GitHub
org (https://github.com/php-arazzo). Composer packages are renamed from
`alama/arazzo-*` to `php-arazzo/arazzo-*`, engine+runtime are merged into a
single `engine` package, events are folded into `runner`, `cli` becomes a thin
OSS port importing `RunnerFacadeInterface` only, and the core↔cli circular
dependency is removed. Published independently from one monorepo via tag-based
subtree splits, with the root directory cleaned by moving agent/tooling
infrastructure to a separate `php-arazzo/dev-tools` repo.

The `laravel-arazzo` package is extracted to its own repo
`php-arazzo/laravel-arazzo` (already exists, empty) rather than staying in the
monorepo. This matches your decision that laravel is a separate package.

This is the foundation the roadmap's PaaS platform builds on: the PHP packages
are the versioned engine foundation. Publishing them cleanly under the org lets
consumers `composer require php-arazzo/arazzo-core` etc. The PaaS platform
depends on these Composer packages (`arazzo-runner` facade, `arazzo-core`
parser/validator, `arazzo-contracts` ports/DTOs) and builds its own cli/web/api/mcp
ports on top.

## Goals

- Rename all 12 surviving monorepo package `name` fields:
  `alama/arazzo-*` → `php-arazzo/arazzo-*` (engine+runtime merged into `engine`;
  events folded into `runner`).
- Merge `packages/engine` + `packages/runtime` into `packages/engine`
  (`php-arazzo/arazzo-engine`); delete `packages/runtime` as composer package.
- Fold `packages/events` into `packages/runner` (`php-arazzo/arazzo-runner`);
  delete `packages/events` as composer package.
- Make `packages/cli` a thin OSS port importing `RunnerFacadeInterface` only
  (not `StepStateMachineEngine`/`WorkflowEngineInterface` directly).
- Remove `alama/arazzo-cli` from `packages/core`'s `require` (breaks the
  shared-services→adapter circular dependency).
- Extract `packages/laravel/` to `php-arazzo/laravel-arazzo` repo; remove laravel
  from monorepo root `composer.json` (require, repositories, autoload, scripts, tests).
- Wire every remaining package's `require` block and root `composer.json` to the
  new names.
- Give every package a `version`, `type: library`, accurate `description`/`license`/`homepage`.
- Publish model: monorepo (12 packages) + subtree splits; tags
  `arazzo-core/1.0.0-alpha.1`, etc.
- Clean release repo root: agent/tooling infra moves to `php-arazzo/dev-tools`;
  `public/` removed; `conformance/baseline.json` moves to dev-tools.
- Move repo to org: `Mohammed-Alama/php-arazzo` → `php-arazzo/php-arazzo`.
- Keep the tree green: composer validate, lock, phpstan, pint, pest all pass
  after the rename+merge.
- Release via `release.sh <core|laravel> <version>` (promotes `[Unreleased]`
  CHANGELOG, bumps version, updates root pin). Laravel release runs in its own repo.
- Add arch tests enforcing: cli→RunnerFacadeInterface only, core does not
  require cli, no circular package deps, framework-agnostic core/engine.

## Non-goals

- Do NOT rename PHP namespaces (`Alama\Arazzo\*` stays) in this release.
  843 files / ~5.5k references; too large and risky to combine with the package
  rename. Flagged as post-release work.
- Do NOT build the PaaS platform (tenancy, billing, console) — that's the
  roadmap's separate track. The OSS packages are the foundation the PaaS
  consumes as Composer dependencies.
- Do NOT change git remotes or push tags in this task; tagging is a separate
  release step.
- Do NOT split the monorepo into 12 repos (Approach B rejected — one repo for
  the engine packages).

## Anti-goals (would make this a failure even if technically works)

- A renamed/merged package that can't be installed via Composer.
- A root `composer.json` that requires `@dev` path repos for everything at
  public-release time (breaks clean-install smoke test). — Mitigation: keep
  `@dev` until first tag, switch to released versions at release time.
- cli importing engine internals directly (`StepStateMachineEngine`,
  `WorkflowEngineInterface`) — arch test enforces cli→RunnerFacadeInterface
  only.
- core requiring `alama/arazzo-cli` — arch test enforces shared services do not
  depend on an adapter package.
- Deleting or overwriting the existing complete `tasks/plan.md` + `todo.md`
  (Software Factory Playbook, different work).

## Constraints

- PHP ^8.4 throughout.
- Monorepo stays one git repo; remote repointing to org is deployment
  (manual GitHub transfer).
- Release gates: pint → phpstan → pest (release skill default).
- `composer.lock` must stay consistent after dependency name changes.
- `tests/scripts/ComposerLockWiringTest.php` fails when a package has both a
  declared version and path pin — root must not pin both.

## Approaches considered

| Approach | Package names | Publishing | Verdict |
|----------|--------------|-----------|---------|
| A (chosen) | `alama/*` → `php-arazzo/*` | Monorepo (12 pkgs) + subtree splits; laravel separate repo | Matches CHANGELOG intent, release skill supports per-package bump, clean org alignment |
| B | `php-arazzo/*` | Separate repo per package | 12 repos × CI × release tooling; too much overhead |
| C | `alama/*` (unchanged) | Monorepo, single shared version | Doesn't match org alignment decision; rejected |

## Parts of the how (decisions made)

1. **Package rename:** all 12 surviving monorepo `packages/*/composer.json`
   `name` → `php-arazzo/arazzo-<pkg>`; `type: None` → `type: library`; add
   `version` (`1.0.0-alpha.1` for all).
2. **Merge engine+runtime into `engine`:** `packages/runtime/src/` →
   `packages/engine/src/Runtime/` (namespaces `Alama\Arazzo\Runtime\*` kept);
   `packages/runtime/composer.json` deleted; engine composer.json absorbs
   runtime's `require` entries.
3. **Fold events into `runner`:** `packages/events/src/` →
   `packages/runner/src/Events/` (namespaces `Alama\Arazzo\Events\*` kept);
   `packages/events/composer.json` deleted; runner composer.json absorbs
   events' `require` entries.
4. **Sibling requires:** every package `require` referencing `alama/arazzo-*`
   → `php-arazzo/arazzo-*` (excluding laravel).
5. **Root composer.json:** 12 `repositories` entries (no laravel),
   `options.versions` keys updated to new names; 12 `require` entries @dev
   (no laravel); description "Not published" removed; autoload/autoload-dev
   drop laravel + tools; scripts drop `@analyse-laravel`/`@test-laravel`.
6. **Cli thin port:** cli imports `RunnerFacadeInterface` only; arch test
   enforces no direct import of `StepStateMachineEngine`/`WorkflowEngineInterface`.
7. **Core circular dep fix:** remove `alama/arazzo-cli` from `packages/core`
   `require`; arch test enforces core does not require cli.
8. **Laravel extraction:** `packages/laravel/` moved to
   `php-arazzo/laravel-arazzo` repo (git subtree split to preserve history).
   Laravel's release runs in that repo, requiring released versions of the 12
   monorepo packages.
9. **Root cleanup:** move `.agents/`, `.claude/`, `.ocx/`, `.scratch/`, `.zed/`,
   `storage/`, `tasks/`, `tools/` to dev-tools. **Keep `.githooks` and root
   `tests/` in the main repo.** Remove `public/` (demo UI). Move
   `conformance/baseline.json` to dev-tools. Keep `packages/`,
   `composer.json`, `composer.lock`, `CHANGELOG.md`, `README.md`, `LICENSE.md`,
   `phpunit.xml.dist`, `pint.json`, `Makefile`, `Dockerfile*`, `.github/`,
   `.gitignore`, `.editorconfig`, `.gitattributes`, `docs/`, root `tests/`,
   `scripts/` (release gates + smoke install stay).
10. **Scripts split (advised):** stay in main repo — `smoke-install.sh`
    (release gate), `quality-gates.php` + `check-baselines.php` (verification
    gates), `ship-plan.sh` (roadmap→CHANGELOG), conformance scripts
    (`conformance-gate.php`, `conformance-score.php`,
    `generate-conformance-matrix.php`, `fetch-conformance-corpus.sh`,
    `ConformanceScorer.php`, `check-conformance-manifest.php`),
    `generate-docs.php` + `generate-docs/` (docs gate). Move to dev-tools —
    `apptree`, `bin/gh`+`install-gh-shim`, `ecosystem/`, `manual_test.php`,
    `phpstorm-inspect.php`, `split_doc.py`, `test_checkout.php`.
11. **`tools/issue-intake` (issue-take tool):** move to dev-tools (consistent:
    maintainer tooling). It can target any org/repo via CLI/env.
12. **PHP namespace:** stays `Alama\Arazzo\*` (out of scope).
13. **Repo move to org:** use GitHub "Transfer repository" from
    `Mohammed-Alama/php-arazzo` → `php-arazzo/php-arazzo` (moves issues, PRs,
    releases, wiki; preserves git history). A simple `git remote set-url` does
    NOT move issues.
14. **Versions:** all packages `1.0.0-alpha.1` for first release.
15. **composer.json wiring:** keep `@dev` path repos until first tag; switch to
    released versions at release time.
16. **Release:** `release.sh core 1.0.0-alpha.1` (etc.) in monorepo;
    `release.sh` in laravel repo for `laravel-arazzo`.

## What's left to the builder

- Exact dependency-safe rename order (leaf packages first, core last).
- Which `scripts/` files to mirror into dev-tools beyond agent infra (advised
  above — confirm).
- Confirmation no in-progress work is lost when `tasks/` moves to dev-tools
  (it's complete already).
- Verify no `packages/core/src/` file imports `Alama\Arazzo\Cli\*` before
  removing cli from core's require (fix any importing code first).

## Open questions (need human answer, can change what gets built)

None — all decisions made.
