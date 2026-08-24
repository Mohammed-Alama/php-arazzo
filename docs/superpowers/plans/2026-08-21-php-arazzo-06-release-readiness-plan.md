# Documentation, Packaging, CI, and Release Readiness Implementation Plan

> **Status (2026-08-24):** verified against the working tree. `- [x]` = implemented and verified by the current suite; inline notes mark partial/not-done items.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the completed PHP packages installable, accurately documented, continuously verified, and ready for the next alpha/beta release.

**Architecture:** Documentation describes the canonical core and adapters. Package metadata keeps Laravel dependencies isolated. CI runs all quality and compatibility gates, and release checks validate clean installation and artifacts.

**Tech Stack:** Composer, GitHub Actions, Laravel Pint, PHPStan, Pest, Laravel/Testbench.

---

## File map

- Modify `README.md`, `packages/core/README.md`, and `packages/laravel/README.md`.
- Modify `packages/core/composer.json`, `packages/laravel/composer.json`, root `composer.json`, and changelogs.
- Modify `.github/workflows/ci.yml`, `Makefile`, and test configuration.
- Create package-install smoke tests under `scripts/` or `packages/*/tests/Installation/`.
- Add a compatibility table and migration notes under `docs/`.

### Task 1: Correct public documentation

- [ ] Update root imports to actual `Runner` and `Resolver` namespaces. _(partial: package READMEs updated piecemeal; packages/core/README.md line ~106 still shows the old one-arg WorkflowExecutor constructor)_
- [ ] Document canonical synchronous, queue, and Laravel examples using the final APIs.
- [ ] Document Arazzo/OpenAPI versions, protocols, selectors, results, errors, retries, outputs, suspension, and persistence.
- [ ] Add a breaking-change and alpha compatibility policy.
- [ ] Add documentation tests or executable examples for the canonical snippets.
- [ ] Commit `docs: update PHP Arazzo execution and compatibility guide`._(not started)_

### Task 2: Normalize package metadata and release artifacts

- [x] Verify package names, versions, runtime dependencies, PSR requirements, autoload rules, licenses, and package descriptions. _(verified repeatedly during SP-binding work; Laravel deps stay isolated to packages/laravel)_
- [x] Confirm development-only dependencies do not appear in runtime requirements.
- [ ] Add changelog entries for the breaking execution-core migration and OpenAPI executor changes. _(CHANGELOG.md exists but lacks entries for the engine consolidation + expression-spelling fixes)_
- [ ] Add PHP/Laravel/OpenAPI compatibility tables.
- [ ] Run clean Composer installation tests for each package; expect successful autoload and example bootstrap.
- [ ] Commit `chore: prepare PHP packages for clean installation`._(not started)_

### Task 3: Fix CI and local verification commands

- [ ] Align `.github/workflows/ci.yml` path filters with actual root/package files and lockfiles.
- [ ] Fix `Makefile` job names so local `act` targets select existing workflow jobs.
- [ ] Add documentation/example and clean-install checks to CI.
- [x] Run `make verify` or its individual equivalent locally; record missing external prerequisites rather than weakening gates. _(equivalent gates run every session: pint, phpstan x2, pest x2)_
- [ ] Commit `ci: align local and GitHub verification gates`._(not started)_

### Task 4: Add release checklist and package smoke test

- [ ] Create `docs/release-checklist.md` covering tests, analysis, formatting, compatibility, package install, license, changelog, and breaking-change review.
- [ ] Add a smoke test that installs or packages core and Laravel in clean temporary projects.
- [ ] Verify no stale namespace examples, nonexistent links, environment artifacts, or untracked generated files remain.
- [x] Run the full CI-equivalent command set. _(green at HEAD: core 669 passed, laravel 56 passed, phpstan/pint clean)_
- [ ] Commit `docs: add PHP Arazzo release checklist`._(not started)_
