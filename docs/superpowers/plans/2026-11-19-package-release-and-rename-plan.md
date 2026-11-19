# Package Release + Rename to php-arazzo/arazzo-* Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename all surviving composer packages from `alama/arazzo-*` to `php-arazzo/arazzo-*`, merge engine+runtime into `engine`, fold events into `runner`, make `cli` a thin OSS port using RunnerFacadeInterface only, fix core↔cli circular dependency, add arch tests, extract laravel to its own repo, clean the root, and prepare for public release under `php-arazzo` org with tags `arazzo-*/1.0.0-alpha.1`.

**Architecture:** 12 composer packages (was 14). contracts = domain (ports+DTOs). core = shared services (parser/validator/document-loader, no cli dep). engine = application core (state machine + execution pipeline + locks/telemetry/state stores/retry — merged engine+runtime). runner = facade (RunnerFacade) + execution machinery + events. cli = thin OSS port (imports RunnerFacadeInterface only). document/evaluation/expression/sources/protocol-* = dependencies. PHP namespaces stay `Alama\Arazzo\*` (out of scope). Composer package names → `php-arazzo/arazzo-*`.

**Tech Stack:** PHP ^8.4, Composer, Pest, PHPStan, Pint.

**Spec:** `docs/superpowers/specs/2026-11-19-package-release-and-rename-design.md`

## Global Constraints

- PHP ^8.4 throughout all packages.
- Surviving packages renamed: `alama/arazzo-*` → `php-arazzo/arazzo-*` (12 packages: contracts, core, engine, runner, cli, document, evaluation, expression, sources, protocol-http, protocol-rpc, protocol-soap).
- engine = merged packages/engine + packages/runtime; namespaces `Alama\Arazzo\Engine\*` + `Alama\Arazzo\Runtime\*` → engine/src. packages/runtime deleted as composer package.
- runner absorbs events; namespaces `Alama\Arazzo\Events\*` → runner/src/Events/. packages/events deleted as composer package.
- RunnerFacade stays in runner; cli imports RunnerFacadeInterface only (not StepStateMachineEngine/WorkflowEngineInterface directly).
- Every surviving package: `type: library`, `version: 1.0.0-alpha.1`, accurate `description`/`license`/`homepage`.
- Root `composer.json` description "Not published" removed; requires 12 packages at `@dev` via path repos (no laravel).
- core: remove `alama/arazzo-cli` from `require` (breaks circular dependency).
- Laravel package extracted to `php-arazzo/laravel-arazzo` repo; removed from root `composer.json`.
- PHP namespaces stay `Alama\Arazzo\*` (out of scope).
- Keep `@dev` path repos until first tag; switch to released versions at release time.
- Do NOT overwrite existing `tasks/plan.md`/`todo.md` (Software Factory Playbook, complete, different work).
- Tree must stay green: composer validate, lock, phpstan, pint, pest pass after rename+merge.

## Review Focus

- A renamed/merged package that can't be installed via Composer (clean-install smoke test must pass).
- Root `composer.json` requiring `@dev` path repos for everything at release time (breaks smoke test).
- `tests/scripts/ComposerLockWiringTest.php` fails when a package has both declared version and path pin — root must not pin both.
- cli must not import StepStateMachineEngine or WorkflowEngineInterface directly — only RunnerFacadeInterface (arch test enforces).
- core must not require alama/arazzo-cli (arch test enforces no circular/shared-services→adapter dep).
- Missing `version` field on any surviving package (subtree split tags need a version to split from).

## Task List

### Phase 1: Rename leaf packages (no alama/arazzo deps except contracts)
- [ ] Task 1: Rename `packages/contracts` → `php-arazzo/arazzo-contracts`
- [ ] Task 2: Rename `packages/evaluation` → `php-arazzo/arazzo-evaluation`
- [ ] Task 3: Rename `packages/expression` → `php-arazzo/arazzo-expression`
- [ ] Task 4: Rename `packages/runtime` → `php-arazzo/arazzo-runtime` (prepare for merge into engine)

### Phase 2: Rename mid packages (depend on Phase 1)
- [ ] Task 5: Rename `packages/document` → `php-arazzo/arazzo-document`
- [ ] Task 6: Rename `packages/sources` → `php-arazzo/arazzo-sources`

### Phase 3: Rename protocol packages
- [ ] Task 7: Rename `packages/protocol-http` → `php-arazzo/arazzo-protocol-http`
- [ ] Task 8: Rename `packages/protocol-rpc` → `php-arazzo/arazzo-protocol-rpc`
- [ ] Task 9: Rename `packages/protocol-soap` → `php-arazzo/arazzo-protocol-soap`

### Phase 4: Rename cli + fix core circular dependency
- [ ] Task 10: Rename `packages/cli` → `php-arazzo/arazzo-cli`; cli imports RunnerFacadeInterface only
- [ ] Task 11: Remove `alama/arazzo-cli` from core's require; fix any core code importing cli

### Phase 5: Merge engine+runtime into `engine` package
- [ ] Task 12: Rename `packages/engine` → `php-arazzo/arazzo-engine`
- [ ] Task 13: Move runtime src into engine; delete packages/runtime; update engine composer.json

### Phase 6: Fold events into `runner`
- [ ] Task 14: Rename `packages/runner` → `php-arazzo/arazzo-runner`
- [ ] Task 15: Move events src into runner; delete packages/events; update runner composer.json

### Phase 7: Root composer.json + verify
- [ ] Task 16: Update root `composer.json` (12 packages @dev, remove laravel, fix description, core no cli)
- [ ] Task 17: Run composer validate + lock check + full test suite; fix breakage

### Phase 8: Root cleanup
- [ ] Task 18: Move `.agents/`, `.claude/`, `.ocx/`, `.scratch/`, `.zed/`, `storage/`, `tasks/`, `tools/` to `/Users/mohammedalama/Code/PHPArazzo/dev-tools`
- [ ] Task 19: Remove `public/` directory
- [ ] Task 20: Move `conformance/baseline.json` to dev-tools

### Phase 9: Arch tests (Q7/Q14)
- [ ] Task 21: Add `tests/Architecture/` tests (no circular deps, cli→RunnerFacadeInterface only, core no cli dep, framework-agnostic core)

### Phase 10: Repo move to org + release prep
- [ ] Task 22: Transfer repo `Mohammed-Alama/php-arazzo` → `php-arazzo/php-arazzo` via GitHub Transfer
- [ ] Task 23: Extract `packages/laravel/` to `/Users/mohammedalama/Code/PHPArazzo/laravel-arazzo` repo (subtree split)
- [ ] Task 24: Run release gates and tag `arazzo-core/1.0.0-alpha.1` (then other packages)

---

## Detailed Tasks — Phase 1

### Task 1: Rename `packages/contracts` → `php-arazzo/arazzo-contracts`

**Files:**
- Modify: `packages/contracts/composer.json`

**Interfaces:**
- Consumes: none (leaf package)
- Produces: package name `php-arazzo/arazzo-contracts`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/contracts/composer.json`: `name` → `php-arazzo/arazzo-contracts`, `type` → `library`, add `version: "1.0.0-alpha.1"`, ensure `license: "MIT"` and `description` present.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm no error for contracts.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/contracts/composer.json
  git commit -m "feat: rename contracts package to php-arazzo/arazzo-contracts"
  ```

### Task 2: Rename `packages/evaluation` → `php-arazzo/arazzo-evaluation`

**Files:**
- Modify: `packages/evaluation/composer.json`

**Interfaces:**
- Consumes: none (contracts only, @dev)
- Produces: package name `php-arazzo/arazzo-evaluation`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/evaluation/composer.json`: `name` → `php-arazzo/arazzo-evaluation`, `type` → `library`, add `version: "1.0.0-alpha.1"`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/evaluation/composer.json
  git commit -m "feat: rename evaluation package to php-arazzo/arazzo-evaluation"
  ```

### Task 3: Rename `packages/expression` → `php-arazzo/arazzo-expression`

**Files:**
- Modify: `packages/expression/composer.json`

**Interfaces:**
- Consumes: none (contracts only, @dev)
- Produces: package name `php-arazzo/arazzo-expression`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/expression/composer.json`: `name` → `php-arazzo/arazzo-expression`, `type` → `library`, add `version: "1.0.0-alpha.1"`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/expression/composer.json
  git commit -m "feat: rename expression package to php-arazzo/arazzo-expression"
  ```

### Task 4: Rename `packages/runtime` → `php-arazzo/arazzo-runtime` (prepare for merge into engine)

**Files:**
- Modify: `packages/runtime/composer.json`

**Interfaces:**
- Consumes: none (contracts only, @dev)
- Produces: package name `php-arazzo/arazzo-runtime`, version `1.0.0-alpha.1`, type `library` (will be merged into engine in Task 13)

- [ ] **Step 1:** Edit `packages/runtime/composer.json`: `name` → `php-arazzo/arazzo-runtime`, `type` → `library`, add `version: "1.0.0-alpha.1"`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/runtime/composer.json
  git commit -m "feat: rename runtime package to php-arazzo/arazzo-runtime"
  ```

---
---

## Detailed Tasks — Phase 2

### Task 5: Rename `packages/document` → `php-arazzo/arazzo-document`

**Files:**
- Modify: `packages/document/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts` (requires), `php-arazzo/arazzo-expression` (requires) — renamed in Tasks 1, 3
- Produces: package name `php-arazzo/arazzo-document`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/document/composer.json`: `name` → `php-arazzo/arazzo-document`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update `require` entries `alama/arazzo-contracts` → `php-arazzo/arazzo-contracts`, `alama/arazzo-expression` → `php-arazzo/arazzo-expression`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/document/composer.json
  git commit -m "feat: rename document package to php-arazzo/arazzo-document"
  ```

### Task 6: Rename `packages/sources` → `php-arazzo/arazzo-sources`

**Files:**
- Modify: `packages/sources/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-document`, `php-arazzo/arazzo-expression` (requires) — renamed in Tasks 1, 5, 3
- Produces: package name `php-arazzo/arazzo-sources`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/sources/composer.json`: `name` → `php-arazzo/arazzo-sources`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update `require` entries `alama/arazzo-contracts`, `alama/arazzo-document`, `alama/arazzo-expression` → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/sources/composer.json
  git commit -m "feat: rename sources package to php-arazzo/arazzo-sources"
  ```

---
---

## Detailed Tasks — Phase 3

### Task 7: Rename `packages/protocol-http` → `php-arazzo/arazzo-protocol-http`

**Files:**
- Modify: `packages/protocol-http/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-expression`, `php-arazzo/arazzo-evaluation`, `php-arazzo/arazzo-engine`, `php-arazzo/arazzo-sources` (requires) — renamed in Tasks 1, 3, 2, 12, 6
- Produces: package name `php-arazzo/arazzo-protocol-http`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/protocol-http/composer.json`: `name` → `php-arazzo/arazzo-protocol-http`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update all `alama/arazzo-*` `require` entries → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/protocol-http/composer.json
  git commit -m "feat: rename protocol-http package to php-arazzo/arazzo-protocol-http"
  ```

### Task 8: Rename `packages/protocol-rpc` → `php-arazzo/arazzo-protocol-rpc`

**Files:**
- Modify: `packages/protocol-rpc/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-document`, `php-arazzo/arazzo-engine`, `php-arazzo/arazzo-evaluation`, `php-arazzo/arazzo-expression`, `php-arazzo/arazzo-sources` (requires) — renamed in Tasks 1, 5, 12, 2, 3, 6
- Produces: package name `php-arazzo/arazzo-protocol-rpc`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/protocol-rpc/composer.json`: `name` → `php-arazzo/arazzo-protocol-rpc`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update all `alama/arazzo-*` `require` entries → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/protocol-rpc/composer.json
  git commit -m "feat: rename protocol-rpc package to php-arazzo/arazzo-protocol-rpc"
  ```

### Task 9: Rename `packages/protocol-soap` → `php-arazzo/arazzo-protocol-soap`

**Files:**
- Modify: `packages/protocol-soap/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-document`, `php-arazzo/arazzo-expression`, `php-arazzo/arazzo-evaluation`, `php-arazzo/arazzo-engine`, `php-arazzo/arazzo-sources` (requires) — renamed in Tasks 1, 5, 3, 2, 12, 6
- Produces: package name `php-arazzo/arazzo-protocol-soap`, version `1.0.0-alpha.1`, type `library`

- [ ] **Step 1:** Edit `packages/protocol-soap/composer.json`: `name` → `php-arazzo/arazzo-protocol-soap`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update all `alama/arazzo-*` `require` entries → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/protocol-soap/composer.json
  git commit -m "feat: rename protocol-soap package to php-arazzo/arazzo-protocol-soap"
  ```

---
---

## Detailed Tasks — Phase 4

### Task 10: Rename `packages/cli` → `php-arazzo/arazzo-cli`; cli imports RunnerFacadeInterface only

**Files:**
- Modify: `packages/cli/composer.json`
- Modify: `packages/cli/src/Console/Command/RunCommand.php` (import RunnerFacadeInterface instead of StepStateMachineEngine/WorkflowEngineInterface directly if it does)

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-document`, `php-arazzo/arazzo-evaluation`, `php-arazzo/arazzo-events`, `php-arazzo/arazzo-expression`, `php-arazzo/arazzo-protocol-http`, `php-arazzo/arazzo-runner`, `php-arazzo/arazzo-runtime`, `php-arazzo/arazzo-sources` (requires)
- Produces: package name `php-arazzo/arazzo-cli`, version `1.0.0-alpha.1`, type `library`; cli imports RunnerFacadeInterface only

- [ ] **Step 1:** Edit `packages/cli/composer.json`: `name` → `php-arazzo/arazzo-cli`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update all `alama/arazzo-*` `require` entries → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** In `packages/cli/src/Console/Command/RunCommand.php`, replace direct imports of `StepStateMachineEngine`/`WorkflowEngineInterface` with import of `RunnerFacadeInterface` from `Alama\Arazzo\Runner\RunnerFacadeInterface`. Use `RunnerFacadeInterface` type hints only.

- [ ] **Step 3:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 4:** Commit.
  ```bash
  git add packages/cli/composer.json packages/cli/src/Console/Command/RunCommand.php
  git commit -m "feat: rename cli package to php-arazzo/arazzo-cli; thin port via RunnerFacadeInterface"
  ```

### Task 11: Remove `alama/arazzo-cli` from core's require; fix any core code importing cli

**Files:**
- Modify: `packages/core/composer.json`
- Modify: any `packages/core/src/*` file importing `Alama\Arazzo\Cli\*`

**Interfaces:**
- Consumes: none (shared services — must not depend on cli adapter)
- Produces: core composer.json no longer requires `alama/arazzo-cli`; no core source imports cli classes

- [ ] **Step 1:** Search `packages/core/src/` for any `use Alama\Arazzo\Cli\` imports. If none, proceed. If found, move that code out of core (into runner or cli) or replace with core-internal equivalent.

- [ ] **Step 2:** Edit `packages/core/composer.json`: remove `"alama/arazzo-cli": "@dev"` from `require`.

- [ ] **Step 3:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 4:** Commit.
  ```bash
  git add packages/core/composer.json
  git commit -m "chore: remove alama/arazzo-cli from core require (break circular dep)"
  ```

---
---

## Detailed Tasks — Phase 5

### Task 12: Rename `packages/engine` → `php-arazzo/arazzo-engine`

**Files:**
- Modify: `packages/engine/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-runtime` (requires) — renamed in Tasks 1, 4
- Produces: package name `php-arazzo/arazzo-engine`, version `1.0.0-alpha.1`, type `library` (will absorb runtime in Task 13)

- [ ] **Step 1:** Edit `packages/engine/composer.json`: `name` → `php-arazzo/arazzo-engine`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update `require` entries `alama/arazzo-contracts` → `php-arazzo/arazzo-contracts`, `alama/arazzo-runtime` → `php-arazzo/arazzo-runtime`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/engine/composer.json
  git commit -m "feat: rename engine package to php-arazzo/arazzo-engine"
  ```

### Task 13: Move runtime src into engine; delete packages/runtime; update engine composer.json

**Files:**
- Move: `packages/runtime/src/*` → `packages/engine/src/Runtime/*` (keep `Alama\Arazzo\Runtime\*` namespaces)
- Delete: `packages/runtime/composer.json`, `packages/runtime/` directory
- Modify: `packages/engine/composer.json` (autoload + require)

**Interfaces:**
- Consumes: runtime code (Task 4 complete)
- Produces: engine namespaces (`Alama\Arazzo\Engine\*` + `Alama\Arazzo\Runtime\*`) now part of `php-arazzo/arazzo-engine` package; `packages/runtime/` removed

- [ ] **Step 1:** Move runtime source into engine:
  ```bash
  mkdir -p packages/engine/src/Runtime
  git mv packages/runtime/src/* packages/engine/src/Runtime/
  ```

- [ ] **Step 2:** Delete runtime package:
  ```bash
  git rm -r packages/runtime
  ```

- [ ] **Step 3:** Update `packages/engine/composer.json`:
  - Add autoload PSR-4 mappings for `Alama\Arazzo\Runtime\` → `src/Runtime/`.
  - Add runtime's former `require` entries to engine's `require`.

- [ ] **Step 4:** Run `composer dump-autoload` + `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 5:** Commit.
  ```bash
  git add -A
  git commit -m "feat: merge runtime into engine package"
  ```

---
---

## Detailed Tasks — Phase 6

### Task 14: Rename `packages/runner` → `php-arazzo/arazzo-runner`

**Files:**
- Modify: `packages/runner/composer.json`

**Interfaces:**
- Consumes: `php-arazzo/arazzo-contracts`, `php-arazzo/arazzo-expression`, `php-arazzo/arazzo-evaluation`, `php-arazzo/arazzo-document`, `php-arazzo/arazzo-events`, `php-arazzo/arazzo-engine`, `php-arazzo/arazzo-sources` (requires)
- Produces: package name `php-arazzo/arazzo-runner`, version `1.0.0-alpha.1`, type `library` (will absorb events in Task 15)

- [ ] **Step 1:** Edit `packages/runner/composer.json`: `name` → `php-arazzo/arazzo-runner`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update all `alama/arazzo-*` `require` entries → `php-arazzo/arazzo-*`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Commit.
  ```bash
  git add packages/runner/composer.json
  git commit -m "feat: rename runner package to php-arazzo/arazzo-runner"
  ```

### Task 15: Rename `packages/events` → `php-arazzo/arazzo-events`; fold events src into runner; delete packages/events

**Files:**
- Modify: `packages/events/composer.json`
- Move: `packages/events/src/*` → `packages/runner/src/Events/*` (keep `Alama\Arazzo\Events\*` namespaces)
- Delete: `packages/events/composer.json`, `packages/events/` directory
- Modify: `packages/runner/composer.json` (autoload + require)

**Interfaces:**
- Consumes: events code (Task 5 complete — wait, events wasn't in Phase 1. Let me add it.)
- Produces: events namespaces (`Alama\Arazzo\Events\*`) now part of `php-arazzo/arazzo-runner` package; `packages/events/` removed

- [ ] **Step 1:** Rename `packages/events/composer.json`: `name` → `php-arazzo/arazzo-events`, `type` → `library`, add `version: "1.0.0-alpha.1"`; update `require` entries `alama/arazzo-contracts` → `php-arazzo/arazzo-contracts`.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Move events source into runner:
  ```bash
  mkdir -p packages/runner/src/Events
  git mv packages/events/src/* packages/runner/src/Events/
  ```

- [ ] **Step 4:** Delete events package:
  ```bash
  git rm -r packages/events
  ```

- [ ] **Step 5:** Update `packages/runner/composer.json`:
  - Add autoload PSR-4 mapping for `Alama\Arazzo\Events\` → `src/Events/`.
  - Add events' former `require` entries to runner's `require`.

- [ ] **Step 6:** Run `composer dump-autoload` + `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 7:** Commit.
  ```bash
  git add -A
  git commit -m "feat: fold events package into runner"
  ```

---
---

## Detailed Tasks — Phase 7

### Task 16: Update root `composer.json` — wire all 12 renamed packages

**Files:**
- Modify: `composer.json` (root)

**Interfaces:**
- Consumes: all 12 renamed package names (Tasks 1–9, 12–15 complete)
- Produces: root `composer.json` requiring `php-arazzo/arazzo-*` @dev via 12 path repos; laravel removed; description "Not published" removed; core no longer requires cli

- [ ] **Step 1:** Edit root `composer.json`:
  - Remove `"alama/laravel-arazzo": "@dev"` from `require`.
  - Remove the laravel `repositories` entry (packages/laravel path repo).
  - For all 12 remaining `repositories` entries, update `options.versions` keys from `alama/arazzo-*` → `php-arazzo/arazzo-*`.
  - For all 12 `require` entries, update package names `alama/arazzo-*` → `php-arazzo/arazzo-*`.
  - Change `description` from "Monorepo hosting alama/arazzo-core + alama/laravel-arazzo. Not published." to a publishable description.
  - Remove laravel from `autoload` (`Alama\Arazzo\Laravel\\` → `packages/laravel/src/`) and `autoload-dev` (`Alama\Arazzo\Laravel\Tests\\` → `packages/laravel/tests/`).
  - Remove `@analyse-laravel` from `analyse` script list and `@test-laravel` from `test` script list.

- [ ] **Step 2:** Run `composer validate --no-check-publish --check-lock` from root; confirm clean.

- [ ] **Step 3:** Run `composer install` to regenerate `composer.lock` with new names; confirm no errors.

- [ ] **Step 4:** Commit.
  ```bash
  git add composer.json composer.lock
  git commit -m "chore: wire root composer.json to php-arazzo/arazzo-* names; drop laravel"
  ```

### Task 17: Run full verification; fix breakage

**Files:**
- Modify: any files needed to restore green tree

**Interfaces:**
- Consumes: root `composer.json` with all 12 renamed packages + engine+runner merges (Task 16)
- Produces: green tree: composer validate, phpstan (all packages), pint --test, pest (all packages) pass

- [ ] **Step 1:** Run `composer validate --no-check-publish --check-lock`; must exit 0.

- [ ] **Step 2:** Run `composer run test` (all packages); all suites green.

- [ ] **Step 3:** Run `composer run analyse` (phpstan all packages); zero errors.

- [ ] **Step 4:** Run `vendor/bin/pint --test` on all packages; zero findings.

- [ ] **Step 5:** If any failures, fix the minimal set of files and re-run the failing gate. Commit fixes.

- [ ] **Step 6:** Commit verification-pass state.
  ```bash
  git add -A
  git commit -m "test: verify green tree after rename + merge"
  ```

---
---

## Detailed Tasks — Phase 8

### Task 18: Move `.agents/`, `.claude/`, `.ocx/`, `.scratch/`, `.zed/`, `storage/`, `tasks/`, `tools/` to dev-tools repo

**Files:**
- Delete (from main repo): `.agents/`, `.claude/`, `.ocx/`, `.scratch/`, `.zed/`, `storage/`, `tasks/`, `tools/`
- Create (in `/Users/mohammedalama/Code/PHPArazzo/dev-tools`): same directories with same contents

**Interfaces:**
- Consumes: main repo root directories (Tasks 1–17 complete, tree green)
- Produces: main repo root cleaned of agent/tooling infra; dev-tools repo holds the moved tooling

- [ ] **Step 1:** Clone `https://github.com/PHP-Arazzo/dev-tools` to `/Users/mohammedalama/Code/PHPArazzo/dev-tools` (already exists as empty repo).

- [ ] **Step 2:** Move directories via git mv (preserves history):
  ```bash
  git mv .agents .claude .ocx .scratch .zed storage tasks tools /Users/mohammedalama/Code/PHPArazzo/dev-tools/
  ```

- [ ] **Step 3:** Update `.gitignore` if any moved paths were referenced there (e.g. `/storage/`, `.superpowers/brainstorm`).

- [ ] **Step 4:** Run `composer validate` + `composer install` to confirm tree still works without moved dirs.

- [ ] **Step 5:** Commit.
  ```bash
  git add -A
  git commit -m "chore: move agent/tooling infra to dev-tools repo"
  ```

### Task 19: Remove `public/` directory (demo Arazzo UI)

**Files:**
- Delete (from main repo): `public/`

**Interfaces:**
- Consumes: none
- Produces: `public/` removed from main repo

- [ ] **Step 1:** Confirm `public/` is only the demo UI build artifacts (`public/build/*`) and not needed by any package or script.

- [ ] **Step 2:** Remove:
  ```bash
  git rm -r public/
  ```

- [ ] **Step 3:** Run `composer validate` + test suite quick check (core tests) to confirm nothing references `public/`.

- [ ] **Step 4:** Commit.
  ```bash
  git commit -m "chore: remove demo UI public/ directory"
  ```

### Task 20: Move `conformance/baseline.json` to dev-tools repo

**Files:**
- Delete (from main repo): `conformance/baseline.json`
- Create (in `/Users/mohammedalama/Code/PHPArazzo/dev-tools`): `conformance/baseline.json`

**Interfaces:**
- Consumes: none
- Produces: `conformance/baseline.json` moved to dev-tools

- [ ] **Step 1:** Confirm `conformance/` only contains `baseline.json` (no other tracked files).

- [ ] **Step 2:** Move:
  ```bash
  git mv conformance/baseline.json /Users/mohammedalama/Code/PHPArazzo/dev-tools/conformance/baseline.json
  ```

- [ ] **Step 3:** Run `composer validate` + confirm no script references `conformance/baseline.json` (scripts/conformance-* operate on the OAI corpus under `packages/core/tests/Conformance/corpus/oai/`, not root `conformance/`).

- [ ] **Step 4:** Commit.
  ```bash
  git commit -m "chore: move conformance baseline to dev-tools repo"
  ```

---
---

## Detailed Tasks — Phase 9

### Task 21: Add `tests/Architecture/` tests enforcing boundaries (Q7/Q14)

**Files:**
- Create: `tests/Architecture/PackageBoundaryTest.php`
- Create: `tests/Architecture/InternalApiTest.php`
- Create: `tests/Architecture/FrameworkAgnosticCoreTest.php`

**Interfaces:**
- Consumes: green tree after rename+merge (Task 17)
- Produces: arch tests that run in CI gate; fail on boundary violations

- [ ] **Step 1:** Write `PackageBoundaryTest.php`: asserts no circular composer/package dependencies among the 12 surviving packages; asserts engine doesn't require runner; asserts runner doesn't require engine as separate (merged).

- [ ] **Step 2:** Write `InternalApiTest.php`: asserts `@internal` classes are not imported outside their own package (e.g. `Document\Validator\RuleSet` not imported by runner/cli).

- [ ] **Step 3:** Write `FrameworkAgnosticCoreTest.php`: asserts `packages/core` and `packages/engine` (engine core) don't import `illuminate/*` or Laravel-specific classes.

- [ ] **Step 4:** Write `CliPortTest.php`: asserts `packages/cli` imports only `RunnerFacadeInterface` from runner (not `StepStateMachineEngine` or `WorkflowEngineInterface` directly).

- [ ] **Step 5:** Write `CoreNoCliDepTest.php`: asserts `packages/core` does not require `php-arazzo/arazzo-cli` and no core source imports `Alama\Arazzo\Cli\*`.

- [ ] **Step 6:** Run `vendor/bin/pest tests/Architecture`; all pass.

- [ ] **Step 7:** Commit.
  ```bash
  git add tests/Architecture/
  git commit -m "test: add architecture boundary tests (Q7/Q14)"
  ```

---
---

## Detailed Tasks — Phase 10

### Task 22: Transfer repo `Mohammed-Alama/php-arazzo` → `php-arazzo/php-arazzo` via GitHub Transfer

**Files:**
- Modify: git remote URL (after transfer completes)

**Interfaces:**
- Consumes: none (GitHub-side action)
- Produces: repo hosted under `php-arazzo` org; issues, PRs, releases, wiki moved; git history preserved

- [ ] **Step 1:** On GitHub, go to `Mohammed-Alama/php-arazzo` → Settings → General → "Transfer repository" → target `php-arazzo`.

- [ ] **Step 2:** After transfer, update local remote:
  ```bash
  git remote set-url origin git@github.com:php-arazzo/php-arazzo.git
  ```

- [ ] **Step 3:** Verify `git fetch origin` works and branches/tags intact.

- [ ] **Note:** Do NOT tag or push in this task — tagging is Task 24.

### Task 23: Extract `packages/laravel/` to `/Users/mohammedalama/Code/PHPArazzo/laravel-arazzo` repo (subtree split)

**Files:**
- Delete (from main repo): `packages/laravel/`
- Create (in `/Users/mohammedalama/Code/PHPArazzo/laravel-arazzo`): full `packages/laravel/` contents with history

**Interfaces:**
- Consumes: `packages/laravel/` (already removed from root composer.json in Task 16)
- Produces: laravel package in its own repo `php-arazzo/laravel-arazzo`

- [ ] **Step 1:** Use `git subtree split` or `git filter-repo` to extract `packages/laravel/` history into `/Users/mohammedalama/Code/PHPArazzo/laravel-arazzo` repo.

- [ ] **Step 2:** Remove `packages/laravel/` from main repo:
  ```bash
  git rm -r packages/laravel/
  ```

- [ ] **Step 3:** Confirm root `composer.json` no longer references laravel (done in Task 16); run `composer validate` + `composer install`.

- [ ] **Step 4:** Commit.
  ```bash
  git commit -m "chore: extract laravel-arazzo to separate repo"
  ```

### Task 24: Run release gates and tag `arazzo-core/1.0.0-alpha.1` (then other packages)

**Files:**
- Modify: `packages/core/composer.json` version bump (if not already 1.0.0-alpha.1), CHANGELOG.md promotion
- Create: git tag `arazzo-core/1.0.0-alpha.1`

**Interfaces:**
- Consumes: green tree (Task 17), root composer.json wired (Task 16)
- Produces: tagged release `arazzo-core/1.0.0-alpha.1`

- [ ] **Step 1:** Ensure `CHANGELOG.md` `## [Unreleased]` has user-visible entries (it does — execution preflight, shared step budget, failure categories, etc.).

- [ ] **Step 2:** Run release skill: `bash .agents/skills/release/scripts/release.sh core 1.0.0-alpha.1` — gates (pint → phpstan → pest), bump version, promote CHANGELOG, update root pin. (Note: `.agents/` moved to dev-tools in Task 18, so run the release script from a local copy or re-add temporarily.)

- [ ] **Step 3:** Run `bash scripts/smoke-install.sh`; must exit 0.

- [ ] **Step 4:** Tag: `git tag -a arazzo-core/1.0.0-alpha.1 -m "Release arazzo-core/1.0.0-alpha.1"`.

- [ ] **Step 5:** Repeat for remaining 11 packages (each `arazzo-*/1.0.0-alpha.1`), updating root pin each time.

- [ ] **Step 6:** (Manual, post-task) Push tags and notify Packagist.

---

## Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Composer can't resolve renamed sibling packages | High | Rename in dependency order; run composer validate after each task |
| Merge breaks autoload (Engine+Runtime namespaces) | High | Map both namespaces to engine/src in engine composer.json; dump-autoload |
| cli imports runner internals directly | High | Arch test enforces cli→RunnerFacadeInterface only |
| core requires cli (circular) | High | Remove alama/arazzo-cli from core require; fix any importing code |
| Overwriting existing tasks/plan.md (Software Factory Playbook) | High | Never touch existing tasks/ files |
| Release tree breaks on clean install | High | smoke-install.sh must exit 0 before any tag |

