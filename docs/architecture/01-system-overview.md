# 01 — System Overview

## Purpose

This document orients a new contributor to `php-arazzo`: what the Arazzo Specification is, how it maps onto this codebase's PHP types, how the monorepo is organized, and the vocabulary used throughout the rest of the architecture docs.

## What Arazzo describes, and how this engine models it

[OpenAPI](https://www.openapis.org/) describes the individual endpoints an API exposes. [Arazzo](https://github.com/OAI/Arazzo-Specification) sits a layer above it: it describes **workflows** — ordered sequences of calls against one or more OpenAPI-described sources, with data flowing from one call's response into the next call's request.

An Arazzo YAML/JSON document parses into an `Alama\Arazzo\Spec\ArazzoDocument` — an immutable, `readonly` value object tree. The spec vocabulary maps directly onto PHP classes in `packages/core/src/Spec/`:

| Arazzo concept | PHP class | Notes |
|---|---|---|
| The document root | `ArazzoDocument` | Holds `info`, `sourceDescriptions`, `workflows`, `components` |
| A source (an OpenAPI or nested Arazzo doc) | `SourceDescription` | Declares a `name`, `url`, and `type` (`openapi` \| `arazzo`) |
| A workflow | `Workflow` | Has `workflowId`, `inputs` (JSON Schema), `steps`, `dependsOn`, `successActions`/`failureActions`, `outputs` |
| A step within a workflow | `Step` | References an operation (`operationId`/`operationPath`) or a nested `workflowId`; carries `parameters`, `requestBody`, `successCriteria`, `onSuccess`/`onFailure`, `outputs`, `dependsOn` |
| A runtime expression, e.g. `{$steps.foo.outputs.id}` | `Expression` | Lazily parsed into an AST — see doc 04 |
| A JSONPath/XPath/regex extraction rule | `Selector` | Alternative to `Expression` for `type`+`selector`-shaped values |
| Reusable component references (`$components.*`) | `Reusable` | Resolved against `Components` at execution/transition time |
| `onSuccess`/`onFailure` action definitions | `Action` subclasses | `SuccessGotoAction`, `SuccessEndAction`, `SubWorkflowSuccessAction`, `FailureGotoAction`, `FailureEndAction`, `RetryAction`, `SubWorkflowFailureAction` — see `Spec/Action/` |

These `Spec/` classes are pure data — they know nothing about HTTP, queues, or persistence. Everything that *does* something with a `Spec\Workflow` lives in `Runner/`.

## Monorepo structure

The repository is a Composer monorepo with two publishable packages plus root-level tooling:

```
php-arazzo/
├── packages/
│   ├── core/       alama/arazzo-core     — framework-agnostic engine
│   └── laravel/    alama/laravel-arazzo  — Laravel bridge
├── docs/           architecture, roadmap, and conformance docs (this directory)
├── scripts/        manual test / dev scripts
└── monorepo-builder.php
```

### `packages/core` — framework-agnostic engine

Declared in `packages/core/composer.json` as `alama/arazzo-core`: *"Framework-agnostic Arazzo 1.0.0/1.1.0 workflow engine core: parser, validator, executor, expression resolver."* It depends only on PSR interfaces (`psr/log`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/simple-cache`, `psr/event-dispatcher`, `psr/container`), plus `softcreatr/jsonpath`, `cebe/php-openapi`, and `symfony/yaml`. It has **no Laravel dependency**. Its top-level namespaces:

- `Alama\Arazzo\Spec` — the document model (above)
- `Alama\Arazzo\Parser` — YAML/JSON → `ArazzoDocument` (doc 02)
- `Alama\Arazzo\Validator` — spec-conformance rules, independent of execution
- `Alama\Arazzo\Resolver` — fetches and resolves external `sourceDescriptions` (doc 05)
- `Alama\Arazzo\Runner` — everything execution-related: `Context`, `Evaluation`, `Execution`, `Events`, `Jobs`, `Normalizer` (doc 02, 03, 04)
- `Alama\Arazzo\Expression` — the expression lexer/parser/AST that `Spec\Expression` and `Runner\Evaluation\ExpressionEvaluator` build on
- `Alama\Arazzo\Generator` — optional AI-assisted Arazzo document generation
- `Alama\Arazzo\Support` — small cross-cutting helpers (event dispatcher, exceptions)

### `packages/laravel` — Laravel bridge

Declared as `alama/laravel-arazzo`: *"Laravel bridge. Deeply integrates the core engine into your Laravel applications with service providers, async queue execution, cache locking, and Eloquent model adapters."* Its job is narrow and deliberate: implement the *interfaces* the core defines, and wire them together via `LaravelArazzoServiceProvider`. See doc 06 for the full binding map.

## The architectural boundary between `core` and `laravel`

This boundary is the single most important thing to internalize before touching either package.

**Core defines contracts; Laravel implements them.** Every piece of infrastructure the engine needs — a queue, a lock, a state store, an event ledger, an HTTP client — is expressed as a PHP interface under `Runner/*/Contracts/` (e.g. `QueueDriverInterface`, `LockManagerInterface`, `StateStoreInterface`, `DefinitionRegistryInterface`, `EventLedgerInterface`, `ExecutionRegistryInterface`, `HttpClientInterface`). Core ships one trivial in-process implementation where it makes sense (`SyncQueueDriver`, `InMemoryDefinitionRegistry`), used mainly for the synchronous execution path and tests.

Laravel provides the production-grade implementations: `LaravelQueueDriver` dispatches onto Laravel's queue, `LaravelRedisLockManager` wraps `Cache::lock()`, `RedisHotStateStore` persists `ExecutionState` in Redis, and the `Persistence\Database*` classes back the registries and event ledger with Eloquent's query builder. None of these classes are referenced by name anywhere in `core` — the wiring only happens in `LaravelArazzoServiceProvider`.

This means: if you're adding new execution behavior, ask first whether it belongs in `core` (spec-mandated, storage-agnostic) or `laravel` (a specific persistence/queue/HTTP strategy). If code in `core` ever needs to `use Illuminate\...`, that's a sign the abstraction has leaked.

## Domain glossary

From `packages/core/CONTEXT.md`, extended with terms used across `Runner/`:

- **Expression Evaluator** — the low-level module that parses and resolves an Arazzo Expression string against the current Workflow Context.
- **Request Compiler** (conceptually: `StepExecutor` + `ReusableParameterResolver` + `OpenApiOperationResolver`) — takes an Arazzo `Step` and evaluates its inputs to produce a concrete HTTP request.
- **Output Extractor** (`ArazzoOutputExtractor`) — processes an HTTP response and extracts values into the Workflow Context based on the step's `outputs` definitions.
- **Criteria Evaluator** (`ArazzoCriteriaEvaluator`) — evaluates `successCriteria` expressions for a step to determine whether the execution succeeded.
- **Schema Validator** (`ArazzoSchemaValidator`) — validates an HTTP response body against the expected OpenAPI schema.
- **Workflow** — a named, ordered graph of steps with its own inputs/outputs (`Spec\Workflow`).
- **Step** — a single operation invocation (HTTP, AsyncAPI, or nested sub-workflow) within a workflow (`Spec\Step`).
- **Source** — an external document (OpenAPI or nested Arazzo) a workflow's steps reference by name (`Spec\SourceDescription`).
- **Execution** — one running instance of a workflow, identified by an `executionId`, whose progress is captured in an `ExecutionState`.
- **Transition** — the outcome of evaluating a completed step's actions: `next`, `retry`, `goto`, `suspend`, or `end` (`Runner\Execution\Transition`, produced by `WorkflowEngine::transition()`).

## Where to go next

- **Doc 02 — Execution Lifecycle**: the path from a raw YAML file to a running, step-executing workflow.
- **Doc 03 — Dependency Graph**: how `dependsOn` (explicit and implicit) becomes a DAG that governs execution order.
- **Doc 04 — Expression Evaluation**: how `{$...}` expressions are parsed and resolved against runtime state.
- **Doc 05 — Source Resolution**: how external OpenAPI/Arazzo documents referenced by `sourceDescriptions` are fetched and parsed.
- **Doc 06 — Laravel Integration**: how the Laravel package wires queues, locks, and persistence into the core engine.
