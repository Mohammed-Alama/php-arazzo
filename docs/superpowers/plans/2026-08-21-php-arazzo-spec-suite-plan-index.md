# PHP-First Arazzo Spec Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved PHP-first Arazzo specification suite in dependency order, with one canonical execution core and verified synchronous, queued, and Laravel adapters.

**Architecture:** The canonical execution plan owns workflow state, transitions, results, and the step budget. Source/OpenAPI and runtime plans provide capabilities consumed by that core. Parser/validator work gates execution preflight. Conformance tests verify all adapters. Release work follows after behavior stabilizes.

**Tech Stack:** PHP 8.4, PSR-7/PSR-18, Pest PHP, PHPStan, Laravel/Testbench, Symfony YAML, `cebe/php-openapi`.

---

## Dependency graph

```text
Plan 1: Canonical execution core
  ├── Plan 2: Source resolution and OpenAPI operations
  ├── Plan 3: Runtime semantics and errors
  └── Plan 4: Parser, validator, and preflight
          └── Plan 5: Conformance and adapter parity tests
                  └── Plan 6: Documentation, packaging, CI, release
```

Plan 1 may begin from existing seams, but its final behavior depends on the contracts stabilized by Plans 2–4. Plan 5 starts only after Plans 1–4 compile together. Plan 6 is last because its examples and support matrix depend on the completed APIs.

## Plan files

1. `2026-08-21-php-arazzo-01-canonical-execution-core-plan.md`
2. `2026-08-21-php-arazzo-02-source-resolution-openapi-plan.md`
3. `2026-08-21-php-arazzo-03-runtime-semantics-plan.md`
4. `2026-08-21-php-arazzo-04-parser-validator-conformance-plan.md`
5. `2026-08-21-php-arazzo-05-testing-adapter-parity-plan.md`
6. `2026-08-21-php-arazzo-06-release-readiness-plan.md`

## Status (2026-08-24)

Verified against the working tree; each plan file carries per-checkbox notes.

| Plan | State |
| --- | --- |
| 1 Canonical execution core | Done. WorkflowEngine is the single decision path (603806a); gaps: budget/call-stack not persisted in queue payload, inline sub-workflow resets shared budget, no clock/sleep abstraction, cycle/depth exceptions lack dedicated tests. |
| 2 Source resolution / OpenAPI | Done except removing cebe/php-openapi (still used by OpenApiDocumentLoader) and circular-source detection in SourceRegistry. Swagger 2.0 + 3.0 + 3.1 normalizers all implemented. |
| 3 Runtime semantics | Mostly done. Gaps: exception-context fields, failure taxonomy classes, raw body/content-type retention, error category field, selector parameter-value evaluation at runtime. Transport failures intentionally become retryable synthetic-500 outcomes. |
| 4 Parser / validator | Tasks 1-2 done (49 rules incl. OfficialSchemaRule). Task 3 preflight stage NOT started. Task 4 fixture set exists; side-effect-count assertions missing. Severity field absent on Error/Warning. |
| 5 Testing / adapter parity | Done. 9 golden fixtures run through sync + queued adapters with normalized parity; deterministic fakes, property tests, mutation-target docs. Infection not installed yet. |
| 6 Release readiness | Not started except metadata verification and full local gate runs. README still shows stale one-arg WorkflowExecutor example. |

## Superseded overlap

The new plans absorb the existing `unify-control-flow`, `circuit-breaker`, and `openapi-executor` plans. Specifically:

- `2026-08-21-unify-control-flow.md` is incorporated into Plan 1 Tasks 1–2, 4, and 5.
- `2026-08-21-circuit-breaker-plan.md` is incorporated into Plan 1 Task 3.
- `2026-08-21-openapi-executor-plan.md` is incorporated into Plan 2 Tasks 2–5.

Workers must not execute those older plans independently; use the numbered plans above to avoid duplicate or conflicting work.

## Global rules

- Use official Arazzo behavior as the authority.
- Breaking changes are allowed during alpha.
- Use TDD: write a focused failing test, run it, implement the smallest change, rerun it, then run the nearest broader suite.
- Do not hide transport errors as HTTP responses.
- Do not put Arazzo decisions in Laravel adapters.
- Commit each completed task with the message specified in its plan.
