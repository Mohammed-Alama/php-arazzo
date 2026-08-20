# Parser, Validator, and Preflight Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure parser and validator behavior is layered, source-aware, and complete before execution begins.

**Architecture:** Parsing creates a faithful Arazzo DTO tree. Structural and semantic rules produce diagnostics. Capability/preflight validation resolves sources, operations, and reusable components without performing HTTP side effects.

**Tech Stack:** PHP 8.4, Symfony YAML, Pest PHP, PHPStan.

---

## File map

- Modify `packages/core/src/Parser/Parser.php`, `ParseContext.php`, DTO parsers, and parser exceptions.
- Modify `packages/core/src/Validator/Validator.php`, `RuleSet.php`, `ValidationResult.php`, and rules under `Validator/Rules/`.
- Create `packages/core/src/Validator/PreflightValidator.php` and capability diagnostics if required.
- Modify parser and validator tests and fixtures under `packages/core/tests/Parser`, `Validator`, and `fixtures/parser`.

### Task 1: Audit parser coverage against DTOs and official fields

- [ ] Build a field matrix from `packages/core/src/Dto/` and parser methods in `Parser.php`.
- [ ] Add failing parser tests for missing fields, wrong types, enums, extensions, reusable references, null/falsy values, and supported versions.
- [ ] Update parser methods to preserve retrieval metadata, extensions, pointers, and references without runtime evaluation.
- [ ] Keep parser errors separate from validator errors and include exact pointers.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Parser`; expect PASS.
- [ ] Commit `refactor: make Arazzo parsing source-aware and complete`.

### Task 2: Complete structural and semantic validation

- [ ] Add failing tests for duplicate IDs, unknown fields, invalid action targets, dependency cycles, invalid operation references, invalid expression contexts, and invalid replacement pointers.
- [ ] Register all active rules in `Validator.php` and ensure disabled rules are observable in the result.
- [ ] Add stable rule codes and severity to `Error.php`, `Warning.php`, and `ValidationResult.php`.
- [ ] Ensure strict mode rejects unsupported content while permissive mode preserves extensions and reports diagnostics.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Validator`; expect PASS.
- [ ] Commit `feat: complete structural and semantic Arazzo validation`.

### Task 3: Add capability validation and execution preflight

- [ ] Add failing tests proving invalid documents fail before source fetches or HTTP requests.
- [ ] Create `PreflightValidator.php` that uses Plan 2 source/operation contracts and Plan 3 expression/selector capability contracts.
- [ ] Resolve source names, operation references, reusable components, supported selector versions, and supported OpenAPI versions.
- [ ] Return diagnostics with rule code, severity, pointer, workflow ID, and step ID; do not execute network operations.
- [ ] Invoke preflight from both synchronous and queued adapters before their first side effect.
- [ ] Run parser, validator, and runner preflight tests; expect PASS.
- [ ] Commit `feat: add execution preflight validation`.

### Task 4: Build conformance fixture set

- [ ] Create fixtures for minimal/full documents, Arazzo 1.0.x/1.1.x, multiple sources, reusable components, dependencies, goto/retry/end/invoke, replacements, escaped pointers, selectors, extensions, invalid references, cycles, duplicates, and unsupported versions.
- [ ] Add a fixture loader that returns document, expected diagnostics, and expected side-effect count.
- [ ] Assert invalid fixtures produce zero HTTP dispatches.
- [ ] Run `cd packages/core && vendor/bin/pest tests/Parser tests/Validator`; expect PASS.
- [ ] Commit `test: add official Arazzo parser and validator fixtures`.
