# 02 — Execution Lifecycle

## Purpose

Trace the core engine's heartbeat end to end: from a YAML/JSON file on disk to a fully executed (or suspended, or failed) workflow run. This is the high-level flow that ties together parsing, dependency resolution, step execution, and transitions.

## Stage 1 — Loading and parsing

**`Loader::load(string $path): RawDocument`** (`Parser/Loader.php`) reads the file, picks a decoder based on file extension (`Enum\Format::fromExtension()`), and decodes into a plain associative array wrapped as `RawDocument`. YAML goes through `SymfonyYamlDecoder`, JSON through `NativeJsonDecoder`. Errors here (`missing file`, `unreadable`, `unsupported extension`, `decode failure`) throw `LoaderException`.

**`Parser::parse(RawDocument $raw): ArazzoDocument`** (`Parser/Parser.php`) walks the decoded array and builds the immutable `Spec\ArazzoDocument` tree described in doc 01. This is a strict, recursive-descent style parser — every field is validated against its expected shape (`requireString`, `optionalList`, `requireObjectMap`, etc.) and reports precise errors via `ParseContext`, which tracks the JSON-pointer-like path to the offending node (e.g. `workflows[0].steps[2].parameters`). Notably:

- String values matching `/^\{\$.+\}$/` are eagerly wrapped as `Spec\Expression` rather than left as plain strings, so downstream code can distinguish "a literal string" from "a runtime expression" by type alone.
- `successActions`/`failureActions`/`parameters` entries with a `reference` key become `Spec\Reusable` — resolved later against `Components`, not at parse time.
- The parser does **not** validate cross-references (e.g. that a `dependsOn` target exists) — that's the `Validator` package's job, run separately and not part of the execution path.

The output is a fully-typed, immutable `ArazzoDocument`. Nothing about HTTP, queues, or state has happened yet.

## Stage 2 — Resolving initial inputs and constructing context

Execution starts from either `WorkflowExecutor::execute()` (synchronous, in-process — used by the manual test script and simple integrations) or `Engine::evaluate()` (asynchronous, queue-driven — used by the Laravel bridge). Both need a `Runner\Context\WorkflowContext`, the mutable-by-replacement carrier of a run's inputs, per-step results, and components:

```php
$context = new WorkflowContext($workflow->workflowId, $inputs);
```

`WorkflowContext` is intentionally not `readonly` at the class level but every mutator (`withStepResult`, `withStepOutput`, `withWorkflowId`, ...) returns a **new** instance rather than mutating in place — the same "with-er" immutability pattern used throughout `Runner/`. This makes context transitions easy to reason about and safe to snapshot for persistence.

For the queue-driven path, the durable counterpart is `Runner\Context\ExecutionState` — a `readonly` value object holding everything needed to resume a run from cold storage: `executionId`, `definitionId`, `workflowId`, `currentStepId`, `inputs`, `stepAttempts`, `stepResults`, `outputs`, `stepsSpent`/`maxSteps` (a hard step budget), `workflowCallStack`/`maxWorkflowDepth` (for nested sub-workflow invocation), and `status`. `ExecutionState::start()` creates the initial state; `ExecutionState::fromArray()`/`toArray()` round-trip it through the `StateStoreInterface` (doc 06).

Workflow-level `inputs` are declared as a JSON Schema on `Spec\Workflow::$inputs`; the engine does not itself perform JSON-Schema validation of caller-supplied inputs at this stage — that responsibility currently lives with the `Validator` package's `WorkflowInputsValidSchemaRule` (spec-conformance checking) rather than a runtime gate.

## Stage 3 — The main step execution loop

There are two execution strategies in the codebase, and understanding why both exist matters:

### A. Synchronous, in-process (`WorkflowExecutor::execute()`)

When no `WorkflowEngine` is injected, `WorkflowExecutor` walks a `DependencyGraph`'s topological order directly (see doc 03) and calls `StepExecutor::execute()` for each step in sequence, dispatching `StepStarted`/`StepExecuted`/`StepFailed` events as it goes. The first failed step's success criteria immediately ends the run with `RunFailed`. This path has no queue, no locking, no persistence — it is one call stack from start to finish. It's what `scripts/manual_test.php` exercises.

### B. Canonical, transition-driven (`WorkflowExecutor::executeCanonically()` / `StepExecutionWorker::handle()`)

When a `WorkflowEngine` **is** injected (the production Laravel configuration always injects one — see doc 06), execution instead becomes a loop of **(execute step) → (compute transition) → (follow transition)**:

1. Resolve the current step from the current workflow.
2. Run it through `StepExecutor::execute()` (or, in the queue-driven worker, through whichever `StepProtocolExecutorInterface` supports it — see "Protocol executors" below).
3. Fold the raw step result into `ExecutionState` via `withStepResult()`.
4. Call `WorkflowEngine::transition($document, $workflow, $step, $state, $criteriaMet)`.
5. Apply the returned `Transition` and either continue the loop (with a new `$stepId`, possibly in a different `$currentWorkflow`), or stop (terminal transition).

`WorkflowEngine` (`Runner/Execution/WorkflowEngine.php`) is deliberately pure: *"It intentionally knows nothing about queues, locks, storage, or events."* Given a completed step attempt, it decides what happens next by evaluating the step's (or, if empty, the workflow's) `onSuccess`/`onFailure` actions in order, short-circuiting on the first whose `criteria` evaluate true:

| Action type | `Transition` produced |
|---|---|
| `RetryAction` | `Transition::retry(...)` — re-targets the same or another step, respecting `retryLimit` capped by `$maxRetryAttempts` (config: `arazzo.retry_ceiling`) and `retryAfter` as a delay |
| `SuccessGotoAction` / `FailureGotoAction` | `Transition::goto(...)` — jumps to a named step, optionally in another workflow |
| `SuccessEndAction` / `FailureEndAction` | `Transition::end($state, 'succeeded'\|'failed')` — terminal |
| `SubWorkflowSuccessAction` / `SubWorkflowFailureAction` | `Transition::goto($state->enterWorkflow(...), null, $workflowId)` — pushes onto `workflowCallStack`, guarded by `assertCanEnter()` against cycles and `maxWorkflowDepth` |
| No action matched, criteria failed | `Transition::end($state, 'failed')` |
| No action matched, criteria met | `Transition::next(...)` to the next step whose `dependsOn` are all satisfied (`nextRunnable()`), or `Transition::end($state, 'succeeded')` if none remain |

A `Reusable` action reference (`$components.successActions.NAME`) is resolved against `ArazzoDocument::$components` inside `actions()` before evaluation; an unresolvable reference throws `GotoTargetNotFoundException`.

`ExecutionState::spendStep()` increments `stepsSpent` on every `transition()` call; exceeding `maxSteps` throws `StepBudgetExceededException` — a hard circuit breaker against runaway `goto`/`retry` loops.

### The queue-driven variant in detail: `StepExecutionWorker`

In production, each step doesn't run inline — it runs as a queued job. `Engine::evaluate()` finds all currently-runnable steps (via `DependencyAnalyzer`, doc 03) and dispatches one `ExecuteStepJob` per step onto the `QueueDriverInterface`. When a worker picks up that job, `StepExecutionWorker::handle()`:

1. Acquires a per-execution lock (`"execution_lock_{$executionId}"`, 30s TTL) via `LockManagerInterface::acquire()` — this serializes concurrent step completions for the *same* execution so `ExecutionState` transitions never race.
2. Reconciles the job's context against whatever's already persisted in `StateStoreInterface` (`reconcileWithPersistedState()`) — needed because the job may have been enqueued before a concurrent step finished.
3. Skips if the step already succeeded (idempotency guard).
4. Loads the `ArazzoDocument` from `DefinitionRegistryInterface` and the target `Workflow` by ID.
5. Picks a `StepProtocolExecutorInterface` implementation that `supports()` the step — currently `SubWorkflowStepExecutor`, `HttpStepExecutor`, `AsyncApiStepExecutor`, tried in that order (see `LaravelArazzoServiceProvider`).
6. If the executor reports `$outcome->suspended` (used for AsyncAPI "wait for a correlated message" steps), the context is marked `Suspended`, persisted, and a `CorrelationPending` event fires — the run pauses here until `CorrelationResumer` picks it back up from an external trigger (e.g. a webhook).
7. Otherwise, folds the outcome into the context, evaluates success criteria, and — if a `WorkflowEngine` is configured — rebuilds an `ExecutionState` from persisted state, calls `transition()`, saves the new state, and either marks the execution complete (`ExecutionRegistryInterface::complete()`) or enqueues the next `ExecuteStepJob`.

Every meaningful state change also appends to `EventLedgerInterface` and dispatches a PSR-14 event (`StepStarted`, `StepExecuted`, `StepFailed`, `RunStarted`, `RunCompleted`, `RunFailed`, `CorrelationPending`) — see `Runner/Events/`.

## Stage 4 — Completion

A run ends when `WorkflowEngine::transition()` returns a terminal `Transition` (`isTerminal()` true for `end` and `suspend`... note: `suspend` pauses rather than truly terminates). On success, `RunCompleted` is dispatched with the aggregated `outputs`; on failure, `RunFailed` carries the triggering exception or a synthesized `RuntimeException` describing which step failed.

## Summary diagram

```
 YAML/JSON file
      │  Loader::load()
      ▼
  RawDocument
      │  Parser::parse()
      ▼
 ArazzoDocument  ──────────────────────────┐
      │                                    │ (DefinitionRegistryInterface,
      │  WorkflowContext / ExecutionState  │  doc 06)
      │  (inputs seeded)                   │
      ▼                                    │
 DependencyGraph / DependencyAnalyzer  ◄───┘   (doc 03)
      │  runnable step(s)
      ▼
 StepExecutor / StepProtocolExecutorInterface   (HTTP call, output extraction — doc 04)
      │  outcome
      ▼
 WorkflowEngine::transition()                   (retry / goto / end / enter sub-workflow)
      │
      ├─▶ next step  ──▶ (loop)
      ├─▶ suspend     ──▶ CorrelationResumer (later)
      └─▶ end         ──▶ RunCompleted / RunFailed
```
