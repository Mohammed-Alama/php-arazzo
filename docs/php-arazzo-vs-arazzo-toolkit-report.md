# PHP Arazzo vs Arazzo Toolkit

## 1. Purpose

This report compares the PHP Arazzo packages in this repository with the `/Users/mohammedalama/Code/Me/arazzo-toolkit` project.

The report answers:

- What the PHP package has.
- What the Arazzo Toolkit has.
- What is missing from each project.
- What is stronger or weaker in each project.
- What the approved specifications must address.
- What the PHP package is expected to provide after the plans are implemented.

The official Arazzo specification is the authority. Toolkit behavior is used only as a reference for useful APIs, fixtures, and implementation patterns.

## 2. Projects Reviewed

### PHP package

- Repository: `php-arazzo`
- Packages:
  - `alama/arazzo-core`
  - `alama/laravel-arazzo`
- Runtime: PHP 8.4
- Current branch: `codex/php-arazzo-spec-suite-docs`
- Current base: merged `main` plus `feat/openapi-executor`
- License: MIT
- Source files: approximately 220 core source files
- Test files: approximately 174 core and Laravel test files

### Arazzo Toolkit

- Repository: `arazzo-toolkit`
- Packages:
  - `@usearazzo/parser`
  - `@usearazzo/resolver`
  - `@usearazzo/validator`
  - `@usearazzo/runner`
- Runtime: TypeScript/JavaScript, Node and browser builds
- Current branch: `main`
- License: Apache-2.0 with `NOTICE` attribution
- Source files: approximately 81 package source files
- Test files: approximately 113 package test files
- `@usearazzo/runner` remains private and unpublished

## 3. Capability Matrix

| Capability | PHP package | Arazzo Toolkit |
|---|---|---|
| Arazzo parsing | Custom PHP DTO parser | ApiDOM-based parser |
| JSON input | Yes | Yes |
| YAML input | Yes | Yes |
| Arazzo 1.0 support | Declared | Declared |
| Arazzo 1.1 support | Declared | Toolkit documents 1.0.0/1.0.1 |
| Source descriptions | Custom resolver | ApiDOM resolver and dereference strategies |
| Multiple named sources | Parsed, runtime resolution incomplete | Registry and source-aware resolution |
| OpenAPI operation lookup | Custom `cebe/php-openapi` lookup | Dedicated OpenAPI 2.0/3.0/3.1 normalizers |
| OpenAPI 2.0 | Not clearly documented as a complete runtime target | Explicitly supported |
| OpenAPI 3.0 | Partially supported through `cebe/php-openapi` | Explicitly supported |
| OpenAPI 3.1 | Partially supported through `cebe/php-openapi` | Explicitly supported |
| Validation | Custom rule engine | Language-service diagnostics and linting |
| Runtime expressions | Custom parser/evaluator | Dedicated runtime-expression evaluator |
| JSONPath | Yes in core | Yes |
| XPath | DOM XPath support with version limits | XPath evaluator with explicit versions |
| Selector runtime use | Incomplete in request paths | More complete evaluator pipeline |
| HTTP execution | PSR-18 and new OpenAPI executor seam | Pluggable HTTP client, fetch default |
| Request serialization | Custom, currently limited | OpenAPI-aware normalization and serialization |
| Retry | Queue path supports retry | Runner supports retry and retry references |
| Goto | Queue path supports goto | Runner supports step and workflow goto |
| End actions | Queue path supports end | Runner supports end |
| Invoke/sub-workflow | PHP DTOs and queue path support | Runner supports sub-workflow composition |
| Workflow dependencies | Dependency graph and queue support | Runner supports dependency execution |
| Synchronous workflow parity | Incomplete | Primary execution path |
| Queue execution | Strong Laravel/PSR infrastructure | Not provided |
| Persistence | State stores, event ledger, registries | In-memory execution state |
| Locks | Lock manager and Laravel cache integration | Not provided |
| Correlation/suspension | Present in PHP queue path | Not a primary toolkit capability |
| Cancellation | Not consistently exposed | `AbortSignal` supported |
| Browser distribution | No | Parser, resolver, validator, and runner build for browser targets |
| Composer/npm packaging | Composer alpha packages | Published parser/resolver/validator; runner private |
| CI | Pint, PHPStan, Pest, Laravel matrix | lint, TypeScript, build, test, CodeQL, release workflow |

## 4. What the PHP Package Has

### Domain model and parsing

The PHP package has a substantial Arazzo DTO model covering documents, workflows, steps, actions, expressions, parameters, request bodies, components, source descriptions, and specification enums.

Relevant areas:

- `packages/core/src/Dto/`
- `packages/core/src/Parser/Parser.php`
- `packages/core/src/Expression/`

The parser supports JSON/YAML input, Arazzo version checks, reusable components, extensions, action parsing, selectors, and source descriptions.

### Validation

The PHP package has a large explicit rule set covering:

- Required fields.
- Unknown fields.
- IDs and uniqueness.
- Source references.
- Workflow and step dependencies.
- Action targets.
- Retry limits.
- Expression syntax and reference resolution.
- Selector versions.
- Request-body replacement targets.
- Arazzo version requirements.

Relevant areas:

- `packages/core/src/Validator/`
- `packages/core/src/Validator/Rules/`

### Runtime and infrastructure

The PHP package has infrastructure that the Toolkit does not provide:

- PSR-18 HTTP integration.
- PSR logging, events, containers, caches, and event dispatchers.
- Queue drivers.
- Persistent execution state.
- Event ledger.
- Execution registry.
- Distributed locking interfaces.
- Idempotency-key injection.
- Correlation-pending and resume support.
- Laravel service provider and adapters.
- Laravel queue jobs.
- Laravel persistence integrations.

Relevant areas:

- `packages/core/src/Runner/`
- `packages/core/src/Events/`
- `packages/laravel/src/`

### Testing

The PHP package has broad tests across DTOs, parser, resolver, validator, runner, events, queue behavior, persistence, and Laravel integration. Its test count is larger than the Toolkit’s, although the tests are split across multiple execution paths.

## 5. What the Arazzo Toolkit Has

### ApiDOM data model

The Toolkit’s parser and resolver are built on SpecLynx ApiDOM. This provides:

- Structured document elements.
- Source metadata.
- Retrieval URI tracking.
- Reference sets.
- Dereferencing strategies.
- Traversal utilities.
- Style and source-map-oriented parsing capabilities.

Relevant areas:

- `arazzo-toolkit/packages/parser/src/`
- `arazzo-toolkit/packages/resolver/src/`

### Source and OpenAPI handling

The Toolkit explicitly supports named source descriptions and multiple OpenAPI versions. The runner separates:

- Document registry.
- Arazzo document extraction.
- OpenAPI operation extraction.
- Operation normalization.
- Parameter resolution.
- Request-body resolution.
- HTTP operation execution.

Relevant areas:

- `arazzo-toolkit/packages/runner/src/registry/`
- `arazzo-toolkit/packages/runner/src/normalizer/`
- `arazzo-toolkit/packages/runner/src/resolver/`
- `arazzo-toolkit/packages/runner/src/executor/`

### Runner semantics

The Toolkit documents and tests:

- Linear workflows.
- `goto` to steps.
- `goto` to workflows.
- `retry` and retry limits.
- Retry references.
- Workflow-level defaults.
- Workflow-level parameters.
- Dependencies.
- Nested workflows.
- Workflow cycles and depth limits.
- Shared step budgets.
- Cancellation.
- Settled terminal results.
- Deterministic injected sleep and clock functions.

Relevant documentation:

- `arazzo-toolkit/packages/runner/README.md`
- `arazzo-toolkit/packages/runner/WORKFLOW_EXECUTOR_PLAN.md`

### Package engineering

The Toolkit provides:

- ESM and CommonJS exports.
- Browser bundles.
- TypeScript declarations.
- API extractor checks.
- ESLint and Prettier.
- Conventional commit validation.
- Dependabot.
- CodeQL.
- npm release automation.

## 6. What Is Missing from the PHP Package

### Critical execution gaps

#### 6.1 Synchronous and queued execution are different engines

`packages/core/src/Runner/WorkflowExecutor.php` currently walks a dependency graph and executes steps in topological order. It does not use the queue path’s full action choreography for `goto`, `retry`, `end`, or sub-workflow transitions.

The queue-oriented behavior lives in `StepOutcomeHandler.php`, `StepExecutionWorker.php`, `Engine.php`, and related classes. This creates semantic drift between the documented synchronous API and the asynchronous path.

#### 6.2 Workflow outputs are not finalized correctly

`WorkflowExecutor.php` aggregates step outputs but returns an empty workflow-output array in the final `ExecutionResult`. Workflow outputs must be evaluated after the canonical terminal state is known.

#### 6.3 Source resolution uses the first source description

`StepExecutor.php` and the current `DefaultOpenApiExecutor.php` still select `sourceDescriptions[0]`. Arazzo operation references must resolve the named source description instead of relying on document order.

#### 6.4 Transport failures are converted to fake responses

`StepExecutor.php` catches broad throwables and records a synthetic status `500`. This hides network failures and can make a transport failure look like an ordinary response-based criteria failure.

### OpenAPI gaps

The current OpenAPI executor has a useful dispatch seam, but still needs:

- Named source lookup.
- Explicit OpenAPI version normalization.
- Server-variable handling.
- Inherited path/operation parameters.
- Cookie parameters.
- OpenAPI parameter styles and explode behavior.
- Non-JSON request bodies.
- Content-type selection.
- Security requirement handling.
- Better operation-path validation.
- Typed resolution and transport errors.

Relevant files:

- `packages/core/src/Runner/DefaultOpenApiExecutor.php`
- `packages/core/src/Runner/OpenApiParser.php`
- `packages/core/src/Runner/Dto/OpenApiPayload.php`

### Runtime gaps

- Selector evaluation is not consistently available in request parameters and body replacements.
- Non-JSON responses are reduced to an empty array by JSON decoding fallback.
- Error categories are not consistently typed.
- Cancellation is not a unified runtime capability.
- Nested result and settled-status semantics are incomplete.
- Request and response context does not preserve all raw data.

### Engineering and documentation gaps

- The root README contains stale namespace examples.
- The public synchronous example does not represent the queue engine’s full behavior.
- Package-level support matrices need to be explicit.
- The local Makefile contains a CI job-name mismatch.
- Conformance fixtures are not yet shared as a formal cross-adapter suite.

## 7. What Is Missing from the Arazzo Toolkit

### Operational infrastructure

The Toolkit does not provide the PHP package’s operational features:

- Persistent execution state.
- Queue drivers.
- Distributed locks.
- Event ledger.
- Laravel integration.
- Database-backed execution registries.
- Correlation and webhook resume infrastructure.
- PSR-based dependency injection contracts.

### Product and release gaps

- `@usearazzo/runner` is private and unpublished.
- Root README sections for CLI, validator, and runner contain placeholders.
- Root documentation references a CLI package path that is not present in the current package tree.
- Cross-document workflow references are explicitly unsupported.
- The runtime is primarily designed for in-memory async execution rather than durable workflow orchestration.

### Version alignment gap

The PHP package documents Arazzo 1.0.0/1.1.0, while the Toolkit documentation currently emphasizes Arazzo 1.0.0/1.0.1. The PHP implementation must follow the official version semantics selected by its own support policy rather than copying the Toolkit’s version labels.

## 8. What Each Project Does Better

### PHP strengths

- Better fit for PHP applications.
- Laravel-native installation and service provider.
- Queue-first operational architecture.
- Durable state and event recording.
- Distributed lock interfaces.
- Correlation and resume workflows.
- PSR interoperability.
- Broader infrastructure and integration tests.
- MIT license compatibility for the project’s intended ecosystem.

### Toolkit strengths

- Cleaner parser/resolver separation.
- ApiDOM-based document fidelity.
- Explicit multi-version OpenAPI normalization.
- More cohesive primary runner path.
- More detailed runner documentation.
- Stronger cancellation semantics.
- Browser and Node distribution.
- Mature TypeScript declaration and npm release pipeline.
- Extensive runner-focused fixture coverage.

## 9. Approved Specifications Required

### Spec 1 — Canonical Execution Core

Purpose: create one state machine for synchronous, queued, and Laravel execution.

Must solve:

- Divergent `WorkflowExecutor` and queue behavior.
- Control-flow duplication.
- Shared retry and step budgets.
- Workflow call stacks and cycle/depth handling.
- Serializable execution state.
- Final result and workflow output aggregation.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-01-canonical-execution-core-plan.md`

### Spec 2 — Source Resolution and OpenAPI Operations

Purpose: make named source and operation resolution standards-compliant.

Must solve:

- First-source selection.
- Multiple source descriptions.
- Relative source URLs.
- Operation reference parsing.
- OpenAPI version normalization.
- Parameter and request serialization.
- Typed dispatch failures.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-02-source-resolution-openapi-plan.md`

### Spec 3 — Runtime Semantics and Error Model

Purpose: unify expressions, selectors, outputs, response context, and errors.

Must solve:

- Selector gaps.
- Falsy-value loss.
- Workflow output finalization.
- Raw response preservation.
- Transport/protocol/criteria error confusion.
- Cancellation and nested result semantics.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-03-runtime-semantics-plan.md`

### Spec 4 — Parser, Validator, and Preflight

Purpose: reject invalid documents before side effects.

Must solve:

- Parser/validator boundary clarity.
- Complete structural and semantic rules.
- Capability validation.
- Source and operation preflight.
- Shared official-spec fixtures.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-04-parser-validator-conformance-plan.md`

### Spec 5 — Testing and Adapter Parity

Purpose: prove synchronous, queue, and Laravel behavior is equivalent where semantics are shared.

Must solve:

- Missing sync/queue parity tests.
- Missing golden workflow fixtures.
- Missing deterministic infrastructure.
- Missing regression coverage for the comparison findings.
- Missing property and mutation coverage for core transitions.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-05-testing-adapter-parity-plan.md`

### Spec 6 — Documentation, Packaging, CI, and Release Readiness

Purpose: make the completed PHP packages installable and accurately documented.

Must solve:

- Stale examples.
- Missing support matrix.
- CI path and Makefile inconsistencies.
- Package metadata and clean-install verification.
- Release checklist and breaking-change documentation.

Plan: `docs/superpowers/plans/2026-08-21-php-arazzo-06-release-readiness-plan.md`

## 10. Expected PHP Package After Plan Completion

After the six plans are implemented, the PHP package should provide:

### Execution

- One official-spec-compliant workflow state machine.
- Equivalent synchronous, queue, and Laravel behavior.
- Correct `goto`, `retry`, `end`, `invoke`, and dependency semantics.
- Shared step budget and workflow-depth protection.
- Durable resumable execution.
- Correct workflow and nested outputs.
- Structured terminal results and settled statuses.

### Sources and HTTP

- Named source-description resolution.
- Multiple OpenAPI source support.
- Explicit operation normalization.
- Supported OpenAPI version matrix.
- Correct parameter serialization.
- Request-body content-type support.
- Preserved raw responses and content types.
- Typed transport and protocol failures.

### Runtime semantics

- Typed expression AST evaluation.
- Selector evaluation in all supported contexts.
- Correct handling of null and falsy values.
- Workflow-level output resolution.
- Stable authoring, transport, protocol, execution, and cancellation errors.

### Validation and quality

- Structural, semantic, capability, and preflight validation layers.
- No network side effects for invalid documents.
- Shared golden fixtures.
- Sync/queue/Laravel parity tests.
- Property and mutation coverage for critical transition logic.

### Developer and consumer experience

- Correct copy-paste documentation.
- Clear PHP/Laravel/OpenAPI support matrix.
- Clean Composer installation.
- Accurate package metadata and changelogs.
- CI that verifies style, analysis, tests, examples, and package installation.
- A clear alpha-to-beta release path.

## 11. Remaining Non-Goals

The PHP-first plans do not require:

- Reimplementing the TypeScript Toolkit.
- Adopting ApiDOM in PHP.
- Publishing the TypeScript runner.
- Adding browser builds to PHP.
- Making Laravel dependencies available in the framework-agnostic core.
- Copying Toolkit behavior that conflicts with official Arazzo semantics.

## 12. Final Assessment

The PHP project has the stronger foundation for durable, framework-integrated workflow execution. The Arazzo Toolkit currently has the clearer standards-oriented parser, resolver, OpenAPI normalization, and in-memory runner pipeline.

The primary PHP risk is not lack of infrastructure; it is semantic duplication between synchronous and asynchronous execution. The six approved plans address that risk first, then close source resolution, runtime, validation, testing, and release gaps in dependency order.
