# Runner Package Split Validation

**Date:** 2026-09-26
**Purpose:** Validate an external analysis proposing to split `Alama\Arazzo\Runner` into layered packages (telemetry, policy, locking, events, HTTP pipeline, engine, protocol executors, state adapters, sync/async runners, facade). Confirm or refute each claim against the actual dependency graph, surface findings the analysis missed, and record what the Phase E / Phase F plans must inherit.
**Method:** Every file (89) and import in `packages/runner/src` was enumerated and its cross-package edges traced. Claims checked: cycle direction, shared-pipeline placement, `Async/` decomposition status, per-module external dependencies, engine independence. Cross-checked against `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md`, `2026-09-08-phase-f-protocol-packages.md`, and the stale `FLATTEN-RUNNER-PLAN.md`.

---

## Verdict

The external analysis is **structurally accurate**. Its three core claims (Execution↔Protocol cycle, deliberately shared request-compilation pipeline stranded in `Execution/`, and an unwired `Async/` decomposition) are all real. Corrections and additions below.

---

## Claim-by-claim

### Claim 1 — Execution↔Protocol is a real two-way cycle

**CONFIRMED, and larger than described.** The cycle is not just `AsyncExecutionGraphAssembler` → 3 executors.

Direction A — `Execution/` → `Protocol/` (1 file, 3 imports):
- `Execution/AsyncExecutionGraphAssembler.php:14-16` imports `Protocol\AsyncApiStepExecutor`, `Protocol\HttpStepExecutor`, `Protocol\SubWorkflowStepExecutor` (instantiated at lines 70, 85, 110).

Direction B — `Protocol/` → concrete `Execution/` classes (5 files, 12 edges, all concrete, none via interface):
- `HttpStepExecutor.php` → `Execution\ExpressionValueResolver`, `Execution\IdempotencyKeyInjector`, `Execution\RequestCompiler`, `Execution\Interfaces\OpenApiExecutorInterface`
- `AsyncApiStepExecutor.php` → `Execution\Data\ExecutionEvaluationInput`, `Execution\ExecutionException`, `Execution\ReusableParameterResolver`
- `SubWorkflowStepExecutor.php` → `Execution\Data\ExecutionEvaluationInput`, `Execution\ExecutionException`, `Execution\ReusableParameterResolver`, `Execution\WorkflowExecutor`
- `SubWorkflowExecutor.php` → `Execution\WorkflowEngine`
- `ProtocolExecutorRegistry.php` → `Execution\Interfaces\ProtocolExecutorRegistryInterface`

The cycle includes a **dead contributor**: `Protocol/SubWorkflowExecutor` (144 lines) and `Protocol/ProtocolExecutorRegistry` are referenced by zero files outside their own namespace (subagent: "SubWorkflowExecutor.php:28, ProtocolExecutorRegistry": 0 src refs; only their own tests). `SubWorkflowExecutor` is a redundant second sub-workflow implementation — the single file responsible for dragging `Execution\WorkflowEngine` into `Protocol/`. Deleting it removes one direction-B edge immediately.

### Claim 2 — RequestCompiler / DefaultOpenApiExecutor are deliberately shared, but sitting inside Execution/

**CONFIRMED.** `packages/runner/src/Execution/` holds the whole shared HTTP pipeline: `RequestCompiler`, `DefaultOpenApiExecutor`, `ParameterSerializer`, `TypeCaster`, `SchemaValidator`, `ResponseSchemaValidator`, `ExpressionValueResolver`, `IdempotencyKeyInjector`.

The docblock on the pipeline is explicit — roughly "consumed by every HTTP pipeline (sync `StepExecutor` and queued `HttpStepExecutor`) so request shapes cannot drift between adapters." `RequestCompiler` has exactly two consumers: `Execution/StepExecutor` (sync) and `Protocol/HttpStepExecutor` (async). `DefaultOpenApiExecutor` reaches `Protocol/` only via `OpenApiExecutorInterface`, so it is clean; the shared-helper placement inside `Execution/` is the cycle source, as the analysis claimed.

### Claim 3 — Async/ is an unfinished decomposition

**CONFIRMED — and it is not "unfinished", it is dead.** The six `packages/runner/src/Async/` classes — `ExecutionStateBuilder`, `PreflightGuard`, `StateReconciler`, `SuspensionHandler`, `TransitionApplier`, `WorkerEvents` — are referenced by **zero** files outside their own namespace in `src/` (verified per-class: 0 src refs each). Only their own tests and `TransitionApplier → WorkerEvents` touch them.

`Execution/StepExecutionWorker` inlines the entire orchestration instead of delegating to them.

The class has drifted behaviorally:
- `Async/SuspensionHandler.php:47`: `$correlationIdValue = is_scalar($evaluated) ? (string) $evaluated : '';`
- `Execution/StepExecutionWorker.php:169`: `$correlationIdValue = (string) $this->expressionResolver->evaluate(...)` — unconditional cast, no scalar guard.

**Answer to the analysis's open question ("mid-refactor or dead/experimental?"):** neither. It is a fully-tested parallel implementation that was never wired in. Phase E's `UnifiedStepCarrier` (Task E3, `2026-09-08-phase-e-runner-oms.md`) replaces `StepExecutionWorker` + `StepOutcomeHandler` entirely, so the correct disposition is **delete `Async/`**, not wire it. Whatever `SuspensionHandler`'s `is_scalar` guard represents must be folded into `UnifiedStepCarrier`.

### Claim 4 — per-module external dependencies

**CONFIRMED.** Internal-edge and external-edge summary (concrete classes only):

| Module | External Arazzo deps | Internal deps |
|---|---|---|
| `Telemetry/` | zero Arazzo deps (OpenTelemetry only) | clean |
| `Policy/` | Contracts only | clean |
| `Infrastructure/` | Contracts only | clean |
| `State/` | Contracts only | clean |
| `Events/` | Contracts only | clean |
| `Jobs/` | Contracts only | clean |
| `Async/` | 1 `Evaluation\Interfaces\ExpressionResolverInterface` | clean |
| `Execution/` | 13 Evaluation, 3 Expression, 11 Document | heaviest sink |
| `Protocol/` | 4 Evaluation, 1 Document | 12 concrete edges into Execution (see Claim 1) |

### Claim 5 — Engine is independently split-clean

**CONFIRMED.** `Execution/WorkflowEngine`, `Execution/Transition`, `Execution/TransitionType`, and the 5 transition exceptions depend only on Contracts + `Evaluation\Interfaces\ExpressionResolverInterface` (an interface — no concrete cross-package edge). `RetryPolicy` (its other runtime collaborator) is Contracts-only. The engine core is the cheapest, highest-value first extraction.

---

## Findings the external analysis missed

These are the genuinely new items, not present in the pasted analysis. Phases E and F must inherit them.

1. **`Protocol/SubWorkflowExecutor` + `ProtocolExecutorRegistry` are dead code** — deleting `SubWorkflowExecutor` alone removes the `Execution\WorkflowEngine → Protocol/` adjacency violation. Its behavior is already covered by the live `SubWorkflowStepExecutor`.

2. **`Document\Validator\Exceptions\PreflightFailureException` leaks into runner.** Imported by all three step-execution paths: `Execution/StepExecutionWorker.php:20` (used for `instanceof` classification), `Async/WorkerEvents.php:9`, `Async/PreflightGuard.php:9`. A validator-internal exception type crossing the document seam — Phase F's document seam must either expose a runner-facing exception or stop using this type.

3. **`Execution/StepOutputExtractor.php` imports `Expression\Enum\ReferenceKind` + `Expression\Interfaces\ExpressionEngineInterface`** — the only remaining Expression-package leak inside `Execution/`. Everything else in Execution reaches the Evaluation package via interfaces.

4. **Correction — no `AsyncApiStepExecutor` nullability mismatch.** An earlier draft claimed `Protocol/AsyncApiStepExecutor.php:37` received a possibly-null `$seams->httpClient`. That is WRONG: `AsyncGraphSeams.php:37` and `AsyncApiStepExecutor.php:37` both use the same non-nullable `Alama\Arazzo\Runner\Infrastructure\Interfaces\HttpClientInterface` — they match. The assembler's own constructor `?ClientInterface $httpClient` (`AsyncExecutionGraphAssembler.php:34`) is a separate PSR type, defaulted at line 40, and never fed to `AsyncApiStepExecutor`. No fix needed in E0.

5. **No cycle-prevention architecture test exists.** `packages/runner/tests/ArchTest.php` only asserts negative rules: no Illuminate, no expression-internals leakage. Nothing asserts that `Execution/` never imports `Protocol/` (or vice versa). A DIP/matcher arch rule should be added before any split (or as part of Phase F) so the ADP violation cannot regress.

---

## Corrections to the external analysis

- **`FLATTEN-RUNNER-PLAN.md` (repo root) is stale / a false source for the "one place for interfaces" philosophy.** That file is the pre-extraction plan targeting `packages/core/src/` top-level directories. The monorepo moved to package-per-concern (`packages/contracts`, `document`, `expression`, `evaluation`, `runner`) via the Phase A–H plan and the spec-backed design. Unless revived, it should not be cited as governing.
  - However, its underlying point survives in a narrower form: contracts already owns `ExecutionState` and `WorkflowContext` (`packages/contracts/src/State/`), while the 5 runner `State/Interfaces` (`StateStoreInterface`, `ExecutionRegistryInterface`, `DefinitionRegistryInterface`, `WritableDefinitionRegistryInterface`, `PendingCorrelationRegistryInterface`) remain in runner. Where the state contracts live needs an explicit decision — Phase A started this, it was not finished.
- **Granularity deviation from Phase F:** the external analysis splits `http-pipeline` and `protocol-executors` into separate package layers. `2026-09-08-phase-f-protocol-packages.md` F1 already merges them: `alama/arazzo-protocol-http` takes normalizers + executors + request compilation + response validation as one vertical slice. The analysis *re-derives* Phases E+F at finer granularity; it does not contradict them, but the 7–10 package layering is finer than the phase plan's vertical slicing. Recommend following the phase plan's vertical slices over the analysis's horizontal layering to avoid a two-axis package explosion.

---

## Expression / Evaluation split status (the "phase C" question)

**No outstanding Expression/Evaluation split work.** `packages/evaluation/` exists with the flat namespace, `ExpressionEvaluatorRegistry` + `CriterionEvaluatorRegistry` (`Registries/`) and `JsonPathExpressionPlugin` + `JsonPathCriterionPlugin` (`Plugins/`) are present and tested. Prior separation work landed (`2026-09-23-expression-evaluation-package-separation.md`, commit `bc47cdc`).

Doc drift to reconcile only:
- `2026-09-08-phase-c-evaluator-plugins.md` still describes an `ExpressionInspector` seam removed in `209f7e8`.
- The SymbolTable relocation into `Document\Validator\Data` (commit `3c98f15`) is post-C housekeeping not reflected in the phase-plan checkbox state. Note phase-plan checkboxes are widely unmaintained (Phase A 77/0, all others 0/N) — do not treat checkbox counts as progress.

---

## Recommended next steps (for Phase E/F)

1. **Delete dead code as pre-work** (independent of any split): `Protocol/SubWorkflowExecutor`, `ProtocolExecutorRegistry`, whole `Async/` directory (after folding the `SuspensionHandler` `is_scalar` guard into the future `UnifiedStepCarrier`). Removes one cycle edge and ~600 lines.
2. **Add the cycle arch test** in `packages/runner/tests/ArchTest.php`. Guard `Execution/` → `Protocol/` first (folded into `phase-e-runner-oms.md` Task E0, green after E4); guard the reverse `Protocol/` → `Execution/` after Phase F1 extraction removes those edges.
3. Follow Phase F1's vertical slice for `arazzo-protocol-http` (normalizers + executors + pipeline + response validation) rather than the analysis's horizontal layers — this resolves the cycle by extraction, matching the shared `RequestCompiler` docblock intent.
4. Move the 5 runner `State/Interfaces` into `contracts/State` (or explicitly decide the seam) — the unfinished tail of Phase A.

---

## Evidence sources

- Full import graph of `packages/runner/src` (89 files) traced 2026-09-26.
- `docs/superpowers/plans/2026-09-08-phase-e-runner-oms.md` (Task E3 `UnifiedStepCarrier`, E4 `OperationExecutorRegistry`).
- `docs/superpowers/plans/2026-09-08-phase-f-protocol-packages.md` (F1 `alama/arazzo-protocol-http`).
- `FLATTEN-RUNNER-PLAN.md` (stale — pre-extraction).
- `packages/runner/tests/ArchTest.php` (current negative rules only).