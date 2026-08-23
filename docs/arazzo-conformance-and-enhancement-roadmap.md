# php-arazzo — Spec Conformance Audit & Enhancement Roadmap

> **Scope:** Comparison of `alama/arazzo-core` against the Arazzo Specification **1.0.0 / 1.0.1 / 1.1.0**, an
> ecosystem survey of Arazzo tooling (as listed on [usearazzo.com/ecosystem](https://usearazzo.com/ecosystem/)
> and the [OAI tooling list](https://github.com/OAI/Arazzo-Specification)), and a prioritized, actionable
> enhancement roadmap.
>
> **Date:** August 2026 · **Package version audited:** `alama/arazzo-core v1.0.0-alpha.1`
>
> All file paths are relative to `packages/core/` unless stated otherwise.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Spec Conformance Matrix](#2-spec-conformance-matrix)
   - 2.1 [Where the package is strong](#21-where-the-package-is-strong-)
   - 2.2 [Critical spec violations](#22-critical-spec-violations-)
   - 2.3 [Minor conformance nits](#23-minor-conformance-nits-)
   - 2.4 [Beyond-spec extensions](#24-beyond-spec-extensions)
3. [Ecosystem Survey & Positioning](#3-ecosystem-survey--positioning)
4. [Enhancement Roadmap](#4-enhancement-roadmap)
   - Phase 0 — Correctness fixes (P0)
   - Phase 1 — Conformance hardening (P1)
   - Phase 2 — Ecosystem differentiation (P2)
5. [Suggested Milestone Ordering](#5-suggested-milestone-ordering)

---

## 1. Executive Summary

The package is architecturally ahead of most Arazzo runners: it has a strict lint-grade validator
(47 rules), a real expression lexer/parser with symbol tables, dependency-graph step scheduling,
durable-execution contracts (ledger/state/locks), PSR-7/11/14/16/18 compliance throughout, and a
Laravel bridge — something **no other runner in the ecosystem offers**.

However, several **spec-mandated behaviors are missing or stubbed at runtime**. The single most severe:

> `simple` success criteria (`condition: $statusCode == 200`) **always evaluate to `true`**
> (`src/Runner/Evaluation/ArazzoCriteriaEvaluator.php:43-47`). Steps can therefore never fail on
> status-code checks, which is the most common assertion form in every published Arazzo example.

Until P0 items land, the engine is best described as "conformant parser + validator, non-conformant
evaluator." The good news: all P0 gaps are localized, testable, and mostly additive.

---

## 2. Spec Conformance Matrix

Legend: ✅ full · 🟡 partial · ❌ missing/broken

### 2.1 Where the package is strong ✅

| Area | Status | Evidence |
|---|---|---|
| Document structure (`arazzo`, `info`, `sourceDescriptions`, `workflows`, `components`) for 1.0.x + 1.1.x | ✅ | `src/Spec/ArazzoDocument.php`, `src/Parser/Parser.php`, `SpecVersion::fromRaw()` |
| YAML + JSON loading, decoder abstraction | ✅ | `src/Parser/Loader.php`, `src/Parser/Decoders/*` |
| Strict unknown-field detection, `x-` extension validation | ✅ | `Validator/Rules/DocUnknownFieldRule.php`, `ExtensionsXPrefixRule.php` |
| Workflow object fields (all 10) | ✅ parsed | `src/Spec/Workflow.php` |
| Step fields incl. AsyncAPI trio (`action`, `channelPath`, `correlationId`) gated to 1.1 | 🟡→✅ parse | `src/Spec/Step.php`, `AsyncApiFieldsRequire11Rule.php` |
| Actions: end / goto (incl. cross-workflow goto) / retry with first-match-wins semantics | ✅ | `Runner/Execution/StepOutcomeHandler.php`, `WorkflowEngine.php` |
| Reusable **actions** (`$components.successActions/failureActions.*`) | ✅ | `StepOutcomeHandler.php:100-121` |
| Runtime expressions: `$url/$method/$statusCode`, header/body parts, JSON Pointer (RFC 6901 `~0/~1`), `$inputs`, `$steps.<id>.outputs/request/response`, `$sourceDescriptions.<n>.url/type`, `{...}` string interpolation | ✅ | `Expression/Lexer.php`, `Expression/Parser.php`, `Runner/Evaluation/ExpressionEvaluator.php`, `JsonPointer.php`, `StringInterpolator.php` |
| Static expression validation via symbol tables (unresolved refs caught pre-run) | ✅ | `Expression/SymbolTable.php`, `StepSymbols.php`, `WorkflowSymbols.php` + 7 validator rules |
| Dependency graph w/ parallel-capable dispatch through queue drivers | ✅ infra | `Runner/Evaluation/DependencyGraph.php`, `Engine::evaluate()` |
| Parameter serialization styles: simple, form, matrix, label, spaceDelimited, pipeDelimited ± explode | 🟡 (deepObject missing) | `Runner/Execution/ParameterSerializer.php` |
| OpenAPI 3.0 normalization (servers, param merge, local `$ref`, requestBodies) | ✅ | `Runner/Normalizer/OpenApi30Normalizer.php` |
| Idempotency-key injection (SHA-256 fingerprint, mutation-methods only) | ➕ extra | `Runner/Execution/IdempotencyKeyInjector.php` |
| Durable execution: event ledger, state stores, lock manager, correlation registry, budgets/depth/cycle guards | ➕ extra (contracts) | `Runner/Execution/Contracts/*`, `ExecutionState.php` |
| Typed PSR-14 events (9 types) + ledger listener | ➕ extra | `Runner/Events/*` |
| Test volume ≈ 488 tests across 12 areas | ✅ | see audit appendix |

### 2.2 Critical spec violations 🔴

These make executions **non-conformant**. Ordered by severity.

| # | Gap | Spec requirement | Evidence |
|---|-----|------------------|----------|
| C1 | **`simple` criteria always pass.** No boolean-expression evaluator; comment says "just returning true for now". `- condition: $statusCode == 200` can never fail a step. | Criterion evaluation MUST apply operator semantics: `== != < > <= >= && \|\| !`, case-insensitive strings, null equality, truthy/falsy (1.1 formalized). | `ArazzoCriteriaEvaluator.php:43-47` |
| C2 | **`$request.query.<name>` / `$request.path.<name>` unparsable** — tokens absent from lexer keywords; parser match arms reject them. Required by ABNF in both versions. | Runtime expression grammar MUST accept `$request.query.name`, `$request.path.id`. | `Expression/Lexer.php:9-13`, `Expression/Parser.php:150-160` |
| C3 | `$outputs.<name>`, `$steps.<id>.inputs.<name>`, `$workflows.<id>.inputs/outputs.<name>` **evaluate to null** (AST nodes exist, evaluator has no cases). Package's own test codifies the null behavior. | All must resolve during workflow scope. | `ExpressionEvaluator.php` (no cases); `tests/Runner/ExpressionEvaluatorTest.php:105-111` |
| C4 | **jsonpath/regex criteria ignore the `context` field** — always evaluated against current step's `$response.body`; null regex context is skipped instead of failing. | `context` MUST be honored (e.g. `context: $steps.other.outputs`); evaluation errors MUST fail deterministically. | `ArazzoCriteriaEvaluator.php:38,49-58` |
| C5 | **XPath criteria throw at runtime** although XPath evaluators exist for Selector Objects (and only xpath-10; spec default is XPath 3.1). | `xpath` criterion type MUST be supported. | `ArazzoCriteriaEvaluator.php:70` vs `Evaluation/Xpath/*` |
| C6 | **Swagger 2.0 documents mis-routed to the 3.0 normalizer**; both `Swagger2Normalizer` and `OpenApi31Normalizer` throw `NotImplementedException`. | Source docs of any declared OpenAPI/Swagger version must resolve or be rejected cleanly *before* execution. | `OpenApiOperationResolver.php:144-148`, `Swagger2Normalizer.php:13`, `OpenApi31Normalizer.php:13` |
| C7 | **AsyncAPI `send` path fatals** — calls undefined method `compileRequest()` on `ExpressionResolverInterface`. Any 1.1 send-step crashes. | 1.1 AsyncAPI steps must execute or reject with a typed error. | `AsyncApiStepExecutor.php:43` |
| C8 | **Workflow-level `parameters` never injected** into step requests. Spec: workflow parameters apply to all steps, overridable but not removable at step level. | Workflow Parameter Object semantics. | `HttpStepExecutor.php:36`, `StepExecutor.php:47` iterate only step parameters |
| C9 | **Reusable parameters unresolved**: `{reference: $components.parameters.x, value}` carried as data but never substituted; `components.inputs` `$ref`s from workflow inputs never resolved. | Reference Object resolution rules. | resolver paths in §7 of audit |
| C10 | **Steps targeting sub-workflows (`step.workflowId`) have no executor** — they fall into the HTTP path and throw "must have either operationId or operationPath". Nested-workflow invocation works only via the beyond-spec `invoke` action. | Step Object with `workflowId` MUST invoke that workflow. | `OpenApiOperationResolver.php:33-34` |
| C11 | **Sync-path Selector outputs throw** ("runtime evaluation requires a separate plugin") while the queue path evaluates them fine — inconsistent behavior between execution modes. | Output extraction semantics must not depend on transport mode. | `ArazzoOutputExtractor.php:46-48` vs `StepOutcomeHandler.php:74-81` |

### 2.3 Minor conformance nits 🟡

| # | Gap | Notes / evidence |
|---|-----|------------------|
| N1 | `retryAfter` restricted to int seconds | Spec allows non-negative decimal seconds and ms units; HTTP `Retry-After` header SHOULD overrule; retry-with-target "run then return" approximated by re-dispatch (`Parser.php:592-598`, `StepOutcomeHandler.php:306`) |
| N2 | No default success criteria behavior | Empty `successCriteria` → returns true; no status-code default convention; no AsyncAPI receive receipt default (1.1 §"Defining Success for Asynchronous Steps") |
| N3 | `deepObject` query style unsupported | Throws `UnsupportedSerializationStyleException` (`ParameterSerializer.php`) |
| N4 | Payload Replacement limited to JSON Pointer targets | 1.1 adds `targetSelectorType: xpath` (`StepExecutor.php:62-86`) |
| N5 | `$message.header/payload[#ptr]`, `$self` expressions missing | 1.1 ABNF additions; absent from lexer keywords |
| N6 | JSON Pointer suffixes only allowed after `.body` | 1.1 allows them on `$inputs/#`, `$outputs/#`, `$steps.x.outputs.y#/p` |
| N7 | Step `timeout` unmodeled | Not in `Step.php`; no receive-timeout handling |
| N8 | Implicit dependencies not inferred | 1.1 Tool Behavior: output references create implicit ordering; only explicit `dependsOn` used |
| N9 | Validator nits | `sourceDescriptions ≥ 1` unenforced; `$self` flagged as unknown field (`DocUnknownFieldRule.php:14` omits it) |
| N10 | Success/Failure Action `parameters` field (1.1 goto actions) unmodeled | — |
| N11 | `channelPath` stored raw, never dereferenced against the AsyncAPI document | `AsyncApiStepExecutor` |
| N12 | Criteria Expression Type `version` parsed+validated but unused in evaluation | `SuccessCriteriaVersionSupportedRule`, `SelectorTypeSupportedRule` |

### 2.4 Beyond-spec extensions

Legitimate additions — but they should be **documented as extensions** (ideally `x-` namespaced) so
conformance claims stay clean:

| Extra | Location | Recommendation |
|---|---|---|
| `invoke` sub-workflow success/failure actions (+ `version` field) | `Spec/Action/SubWorkflow*Action.php`, `SubWorkflowInvoker.php` | Rename to `x-invoke` or keep + document as proprietary extension; add conformance-mode flag that rejects them |
| `in: body` parameter location enum case | `Spec/Enum/ParameterIn.php` | Remove from strict mode |
| `x-idempotency-key`, `x-idempotency-header`, `x-strict-validation` step flags | consumed at `Parser.php:321-323` | Fine — already `x-` prefixed ✔ |
| Durable-execution machinery, budgets, depth guards | `Runner/*` | Pure addition ✔ |
| AI generator with **dormant license gate** (zero call sites!) | `Generator/ArazzoGenerator.php`, `License/*` | Wire the gate or remove the illusion of one (see P2-6) |

---

## 3. Ecosystem Survey & Positioning

Sources: [usearazzo.com/ecosystem](https://usearazzo.com/ecosystem/) (reviewed 2026-08-22),
[OAI README tooling list](https://github.com/OAI/Arazzo-Specification#tooling), openapi.tools.

**php-arazzo is already listed** under "Run & test" — visibility is established.

### Competitive landscape

| Category | Tools | Your position |
|---|---|---|
| **Runners** | Redocly *Respect CLI*, Jentic *Arazzo Runner* (Python), *arazzo-cli* (executor + debugger + **MCP server**), *Itarazzo* (Java), *Specmatic* (authoring/mocking/testing) | **Only PHP/Laravel engine in existence.** Real moat — nobody else covers Laravel queues/Eloquent persistence. |
| **Validators/Linters** | Jentic *arazzo-validator*, Redocly CLI, Spectral arazzo ruleset, Speakeasy Go packages | Your rule suite is competitive; lacks official JSON Schema structural layer (P2-4) |
| **Expression/Criterion libraries** | swaggerexpert *arazzo-runtime-expression* (ABNF-exact), *arazzo-criterion* | Use these as **reference oracles** when implementing C1–C5, not competitors |
| **Generators** | Jentic generator (deterministic pattern-analysis), JaredCE | Yours is AI-only, output unvalidated (see P2-5) |
| **Editors/Viz** | Jentic Editor/UI, Symplr editor, API Flows Studio, apitapviz (Markdown/Mermaid) | None in PHP — optional niche (P2-7) |
| **Converters** | arazzo2openapi, pyarazzo | None in PHP |

### Strategic takeaways

1. **Conformance is table stakes.** Respect CLI publishes a conformance matrix; buyers compare.
2. **The agentic angle is where Arazzo is headed** (OAI roadmap: MCP/A2A step support; arazzo-cli ships
   an MCP server today). A Laravel-native workflow engine with durable state is *uniquely suited* to
   long-running agent workflows — lean in (P2-2).
3. **Nobody serves the PHP ecosystem yet.** First-mover advantage decays fast; P0 speed matters.

---

## 4. Enhancement Roadmap

Each item: *what → why → how → verify*. Items are ordered within phases.

---

### PHASE 0 — Correctness (blocks everything else)

#### P0-1 · Implement `simple` criterion evaluation ⭐ highest impact

- **What:** Replace the always-true stub with a real evaluator for the Condition Expression grammar.
- **Why:** Without it, no workflow can fail on the most common assertion (`$statusCode == 200`). This
  single fix moves the engine from "demo" to "conformant."
- **Grammar to support (per spec):**
  - Comparators: `==`, `!=`, `<`, `<=`, `>`, `>=`
  - Boolean combinators: `&&`, `||`, unary `!`, parentheses grouping
  - Operands: runtime expressions (left side), literals (string/number/boolean/null) on right
  - String comparison case-insensitive; explicit `null` equality; truthy/falsy semantics as formalized in 1.1
- **How:**
  1. Extend the existing expression stack (`Expression/Lexer.php`, `Expression/Parser.php`) with
     comparator/combinator token kinds — do NOT hand-roll regex matching; you already have a lexer/parser pair.
  2. Add AST node types: `BinaryOp`, `UnaryNot`, `Literal`.
  3. Implement `SimpleCriteriaEvaluator` behind the existing `CriteriaEvaluatorInterface`
     (`Runner/Evaluation/Contracts/`), injected into `ArazzoCriteriaEvaluator` (keep it as dispatcher by type).
  4. Evaluation errors (type mismatches, unknown refs) → return `false` + record reason on the step result,
     per spec's deterministic-fail rule.
- **Verify:** Port test vectors from swaggerexpert/**arazzo-criterion** (ABNF-exact reference). Add fixtures:
  status codes, string case-insensitivity, null equality, `&&`/`||` precedence, error-fails-case.

#### P0-2 · Complete runtime-expression coverage in the evaluator

- **What:** Make every AST node the parser accepts actually evaluate.
- **Why:** Parser/evaluator parity is a correctness contract; currently `$outputs.x` silently yields `null`,
  which poisons downstream criteria and outputs.
- **Missing cases:** `$outputs.<name>` · `$steps.<id>.inputs.<name>` · `$workflows.<id>.inputs/outputs.<name>`
  (requires workflow-symbol context injection) · `$components.successActions/failureActions` name lookups.
- **How:**
  1. Thread a workflow-scoped symbols object (you already have `WorkflowSymbols.php`) into `ExpressionEvaluator`.
  2. Add match arms for `OutputRef`, `InputPart` under `StepRef`, `WorkflowRef`.
  3. Update the test at `tests/Runner/ExpressionEvaluatorTest.php:105-111` that currently asserts null —
     it documents the bug as behavior.
- **Verify:** Table-driven tests per expression form × context availability; assert throws-or-null policy explicitly.

#### P0-3 · Lexer/parser: `$request.query.*` and `$request.path.*`

- **What:** Add `query` and `path` to the lexer keyword set; extend `parseHttpPart` match arms;
  evaluate from the captured request (query array / matched path params).
- **How:** The request object must retain resolved path params — ensure `HttpStepExecutor` passes them
  into the evaluation context (they're currently consumed for templating then dropped).
- **Verify:** Round-trip test: build request with `?page=2`, assert `$request.query.page` → `"2"` (string),
  `$request.path.id` from template `/pets/{id}`.

#### P0-4 · Honor criterion `context` + deterministic failure on eval errors

- **What:** `jsonpath`/`regex` criteria must evaluate against `context` when present; missing/null context
  MUST fail the criterion (not skip).
- **How:** In `ArazzoCriteriaEvaluator`, resolve the `context` expression first (falls back to
  `$response.body` only when absent, which matches common practice), then run the selector against that node.
- **Verify:** Cross-step criterion: step B asserts on `context: $steps.a.outputs.flag`.

#### P0-5 · XPath criteria at runtime

- **What:** Route `xpath` criteria to the existing XPath evaluators instead of throwing.
- **How:** `DomXpathEvaluator` supports xpath-10 today; wire type `version` (parsed already) to select
  evaluator; for 3.1 documents either implement via `DOMXPath` subset + document limitations, or reject
  with a typed `UnsupportedCriterionTypeException` naming the version (explicit > silent wrong).

#### P0-6 · Fix source-document routing (Swagger 2.0 / OpenAPI 3.1)

- **What:** Stop routing Swagger 2.0 through the 3.0 normalizer.
- **How (minimum viable):** Detect `swagger: 2.0` and throw a typed `UnsupportedSourceVersionException`
  *at validation time* (fail fast, clear message). Then implement normalizers incrementally:
  - **Swagger 2.0:** map `basePath`+`host`→servers, `parameters`→operation params, `securityDefinitions`;
    request bodies live directly on operations (`in: body`).
  - **OpenAPI 3.1:** mostly a passthrough + JSON Schema 2020-12 dialect notes; webhooks section.
- **Verify:** Fixture corpus: same petstore flow expressed in 2.0, 3.0, 3.1 — identical execution results.

#### P0-7 · Fix AsyncAPI send path

- **What:** `AsyncApiStepExecutor.php:43` calls a method that doesn't exist on the interface.
- **How:** Define the message-compilation contract on `ExpressionResolverInterface` (or inject a dedicated
  `MessageResolver`), implement payload compilation from `channelPath` bindings, and dereference
  `channelPath` against the loaded AsyncAPI document before sending. Also validate `correlationId`
  locations exist in the document.
- **Verify:** 1.1 fixture with AsyncAPI source description: send → pending-correlation → resume round trip
  (your `CorrelationResumer` machinery already exists — this makes it reachable).

#### P0-8 · Sub-workflow steps (`step.workflowId`) get an executor

- **What:** Steps whose target is `workflowId` currently crash in the HTTP path.
- **How:** You already have `SubWorkflowInvoker` (used by invoke-actions). Generalize: register a
  `SubWorkflowStepExecutor` checked before `HttpStepExecutor.supports()`. Preserve parent-context scoping
  (`forChildInvocation`), outputs mapping, and depth/cycle guards (`WorkflowDepthExceededException`,
  `WorkflowCycleException`) — those already exist.
- **Verify:** Official OAI examples include nested workflows; add one to `tests/fixtures/valid/`.

#### P0-9 · Apply workflow-level parameters

- **What:** Merge workflow `parameters` beneath step `parameters` (step wins; workflow params cannot be
  removed) before bucket-routing into path/query/header/cookie.
- **How:** Single merge point in `StepExecutor`/`HttpStepExecutor` parameter collection — keep it pure
  (merge → route) so it stays unit-testable.
- **Verify:** Workflow declares `apiToken` header; step overrides `page` query; assert final request.

#### P0-10 · Resolve reusable objects at runtime

- **What:** Substitute `{reference: $components.parameters.x}` parameter reusables at execution prep;
  resolve `components.inputs` `$ref`s inside workflow inputs schema.
- **How:** Resolve once at document-load time into a normalized view (cheaper than per-step), keeping raw
  DTOs intact for introspection. Guard against nested-reference loops (validator already rejects nesting
  in components — mirror that check for safety).

#### P0-11 · Unify Selector-based output extraction

- **What:** Sync path must evaluate Selectors exactly like the queue path.
- **How:** Inject the same selector-evaluation service used by `StepOutcomeHandler` into
  `ArazzoOutputExtractor`; delete the "separate plugin" RuntimeException.
- **Verify:** Same fixture executed via sync executor and queue driver produces identical outputs.

---

### PHASE 1 — Conformance hardening

#### P1-1 · Retry semantics upgrade
Decimal `retryAfter` (float seconds), millisecond units (`500ms`), honor HTTP `Retry-After` header
(seconds or HTTP-date) over the declared value, implement retry-with-target "execute reference then
return" properly, and add configurable backoff strategy (fixed/exponential+jitter) as an engine option —
the spec minimum is fixed delay; backoff is your operational edge.

#### P1-2 · Default success behaviors
Define documented defaults: empty `successCriteria` → 2xx check for HTTP steps; AsyncAPI receive steps →
receipt-based default per 1.1 §"Defining Success for Asynchronous Steps".

#### P1-3 · 1.1 expression completeness
`$message.header/payload[#ptr]`, `$self` expression, JSON Pointer suffixes on `$inputs/#`, `$outputs/#`,
`$steps.<id>.outputs.<name>#/ptr`. Lexer keywords + AST + evaluator + symbol-table rules together
(they're designed as a set — update all four layers per feature).

#### P1-4 · Step `timeout` (1.1)
Model on `Step.php`, enforce in executors (HTTP client timeout; receive-step deadline feeding the
pending-correlation expiry), surface timeout failures as step failures compatible with `onFailure` chains.

#### P1-5 · Implicit dependencies (1.1 Tool Behavior)
Infer ordering edges from output references (`$steps.x.outputs.*` appearing in a later step) — extend
`DependencyAnalyzer` to emit these as synthetic `dependsOn` edges; add the recommended validation warning
for forward references under sequential execution.

#### P1-6 · Serialization & replacement completeness
Add `deepObject` to `ParameterSerializer`; support `targetSelectorType: xpath` in Payload Replacements
(reuse P0-5 infrastructure); model Success/Failure Action `parameters` field (1.1 goto).

#### P1-7 · Validator closure items
`sourceDescriptions ≥ 1` rule; add `$self` to known root fields; AsyncAPI receive default-success rule;
criteria-version-aware warnings. Each is a small isolated rule — good first-contribution candidates.

#### P1-8 · Structural JSON Schema validation layer
Validate documents against the official schemas ([1.1 iteration 2026-04-15](https://spec.openapis.org/arazzo/1.1/schema/2026-04-15),
[1.0 iteration 2025-10-15](https://spec.openapis.org/arazzo/1.0/schema/2025-10-15)) *before* semantic
rules, using opis/json-schema or justinrainbow/json-schema behind your existing `ErrorCollector` API so
error UX doesn't change. This mirrors how jentic-arazzo-validator layers its pipeline.

---

### PHASE 2 — Ecosystem differentiation

#### P2-1 · CLI binary (`bin/arazzo`)
`validate <file>`, `run <file> --workflow <id> --input '@json'`, `list`, `explain` (dependency graph dump).
Zero new engine code — thin console wrapper over Loader→Validator→Engine using symfony/console.
Table stakes versus respect-cli/arazzo-cli; makes CI adoption trivial.

#### P2-2 · MCP server exposure
Expose workflows as tools to AI agents (Model Context Protocol), mirroring arazzo-cli. Natural fit: your
durable execution (ledger/resume) handles long-running agent sessions that other runners can't. Start
read-only (`list`, `describe`, dry-run) before exposing live execution.

#### P2-3 · Public conformance harness
CI job running the [official OAI 1.0 examples](https://github.com/OAI/Arazzo-Specification/tree/main/examples/1.0.0)
plus a slice of [jentic-public-apis workflows](https://github.com/jentic/jentic-public-apis/tree/main/workflows)
(hundreds of real-world docs) against mock servers; publish a conformance matrix in the README. This is
how Respect CLI earns trust — copy the playbook.

#### P2-4 · Mermaid/markdown renderer
`arazzo render doc.md|flow.mmd` — cheap, high-visibility (apitapviz proves demand), great for docs sites.

#### P2-5 · Generator output self-validation
Pipe `ArazzoGenerator` output through your own Validator before returning; retry-on-invalid loop with
error feedback to the model. Also offer a deterministic (non-AI) generator mode — Jentic's approach —
for users who can't ship prompts to third parties.

#### P2-6 · Decide on the license gate
`LicenseVerifierInterface` has zero call sites — the "Pro" gate enforces nothing. Either wire it around
the Generator (intended design) or remove it. Dormant auth code is a security-review smell.

#### P2-7 · Distribution & discoverability
Split-packagist metadata polish, `arazzo` keyword on both packages, submit to
[openapi.tools](https://openapi.tools/?arazzo=true) and usearazzo.com submission form, badge the
ecosystem listing in your README.

---

## 5. Suggested Milestone Ordering

```
M1 "Steps can fail"          → P0-1, P0-4, P0-11        (correctness core)
M2 "Expressions complete"    → P0-2, P0-3                (parser/evaluator parity)
M3 "Every step runs"         → P0-6, P0-7, P0-8, P0-10   (no more crash paths)
M4 "Spec-complete basics"    → P0-9, P1-1..P1-4          (semantics polish)
M5 "Provable conformance"    → P1-5..P1-8, P2-3          (harness + matrix)
M6 "Ecosystem presence"      → P2-1, P2-2, P2-4..P2-7    (distribution)
```

Rationale: M1 alone changes the engine's fundamental trustworthiness; M2/M3 eliminate every
crash-or-silently-wrong path found in the audit; M5 converts internal fixes into public proof.

---

## Appendix · Audit Method

Static exploration of `packages/core/src` (≈180 classes) cross-checked against Arazzo 1.0.1 and 1.1.0
spec texts; runtime-behavior findings verified against call sites and tests (≈488 tests inventoried);
ecosystem facts from usearazzo.com/ecosystem (reviewed 2026-08-22) and the OAI repository tooling list.
Known latent defect inventory: 2 `NotImplementedException` throwers, 1 undefined-method fatal,
4 inline soft-TODO stubs, 1 dormant license hook.
