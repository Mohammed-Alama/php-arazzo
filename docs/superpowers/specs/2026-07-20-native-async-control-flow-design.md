# Native Asynchronous Control Flow — Design

**Roadmap doc:** [03-native-async-control-flow.md](../roadmap/03-native-async-control-flow.md)
**Depends on:** [02 — CQRS & Event-Sourced Persistence](../roadmap/02-cqrs-event-sourced-persistence.md) — **not yet implemented** (plan written, zero tasks executed). This design is written against doc 02's *target* shape: `WorkflowContext` with `workflowId`/`executionId`, `DefinitionRegistryInterface` keyed on `ArazzoDocument`, `EventLedgerInterface`, `ExecutionRegistryInterface`, and the rewritten `StepExecutionWorker::handle()` exactly as specified in [docs/superpowers/plans/2026-07-20-cqrs-event-sourced-persistence.md](../plans/2026-07-20-cqrs-event-sourced-persistence.md) Task 13. Doc 02 must land first; this spec's implementation plan builds on top of it, not on today's actual code.

## Goals

1. Parse and act on `retryAfter`/`successCriteria`/`onSuccess`/`onFailure` from the Arazzo document at runtime — sleep-and-requeue retries, same-workflow and cross-workflow `goto`, and `end` actions.
2. AsyncAPI `action: receive` steps suspend execution until a matching inbound webhook event arrives; `action: send` steps publish and continue.
3. Fix the two correctness gaps flagged in doc 03's "Existing code" note: double-dispatch/lost-update on diamond (fan-in) DAGs, and `ExecuteStepJob` not being a real Laravel `ShouldQueue` job (so delayed retries silently don't work today).

Out of scope: SLA/dead-letter monitoring (doc 06), saga compensation beyond a plain cross-workflow `goto` (doc 07), the definition-registry persistence itself (doc 02's job — consumed here, not built here).

## Current state (baseline this design modifies)

- `Step` DTO already parses `successCriteria`, `onSuccess`/`onFailure` (`SuccessAction`/`FailureAction` unions of `RetryAction`/`*GotoAction`/`*EndAction`), fully — no parser work needed.
- `ArazzoExpressionResolver::evaluateSuccessCriteria()` exists but its `Simple` criterion type is a stub that always returns `true` (no boolean-expression parser). Fixing that is **not** part of this spec — logged as a known limitation; `Regex` and `JsonPath` criteria work today and are sufficient to drive retry/goto/end decisions in the interim.
- Nothing in `StepExecutionWorker` reads `onSuccess`/`onFailure` at all — a step either "succeeds" (any HTTP response, regardless of status/criteria) and choreography continues unconditionally, or the job throws and Laravel's own queue retry/backoff takes over blindly, with no Arazzo-level semantics.
- `QueueDriverInterface::dispatch(object $job, int $delaySeconds = 0)` already has a delay parameter and `LaravelQueueDriver` already calls `Queue::later()` — but `ExecuteStepJob` is a plain object, not `ShouldQueue`, so this delayed path does not actually work against a real Laravel queue connection today.
- `StepExecutionWorker`'s lock key is `workflow_lock_{definitionId}` — shared across every concurrent *execution* of that definition — and `Engine::evaluate()` runs against the job's own in-memory context snapshot rather than reloaded persisted state. On a diamond DAG (`A → {B, C} → D`), whichever of B/C saves second overwrites the other's persisted result, and `D` never gets dispatched (lost update / starvation), not just theoretical double-dispatch.
- No HTTP routes exist for anything webhook-related. The package does already register routes elsewhere (`LaravelArazzoServiceProvider::packageBooted()`), so this is new surface following an established pattern, not a new pattern.

## Data model changes

### `WorkflowContext` — per-step status and attempt count

Each entry in `WorkflowContext::$steps[$stepId]` gains two keys, written via new immutable withers:

- `status`: new `StepStatus` enum — `Pending | Succeeded | Failed | Retrying | Suspended`.
- `attempts`: int, retry counter.

```php
enum StepStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Suspended = 'suspended';
}
```

New methods: `withStepStatus(string $stepId, StepStatus $status): self`, `withStepAttemptIncremented(string $stepId): self`, `getStepStatus(string $stepId): ?StepStatus`, `getStepAttempts(string $stepId): int`.

**Idempotency check changes.** Doc 02's `StepExecutionWorker` skips a step if `array_key_exists($stepId, $context->getSteps())`. That check can't distinguish "succeeded, never touch again" from "failed once, retry pending" from "explicitly targeted by a goto, re-run it" — all three leave a `steps[$stepId]` entry. This spec changes the skip condition to `getStepStatus($stepId) === StepStatus::Succeeded`. A `goto` back to an already-succeeded step explicitly resets that step's status to `Pending` before dispatch (see below), which is what makes loop-back goto patterns re-executable instead of silently skipped.

**`DependencyAnalyzer` needs the identical fix.** `getRunnableSteps()` has the same key-existence pattern (`$completedStepIds = array_keys($context->getSteps())`) to decide both "is this step itself done" and "are this step's dependencies satisfied." Left as-is, a step sitting at `Retrying`/`Suspended` would be wrongly treated as complete (blocking retry/resume from ever re-entering it through choreography), and a goto's `Pending` reset on an already-succeeded step would be wrongly treated as still un-run by anything depending on it. This spec changes `getRunnableSteps()` to filter/gate on `getStepStatus($stepId) === StepStatus::Succeeded` in both places it currently checks key presence.

### `ExecutionRegistryInterface` — terminal status

Doc 02 ships `start(string $executionId, string $definitionId, string $workflowId): void` only — nothing ever marks an execution finished. This spec adds:

```php
public function complete(string $executionId, ExecutionStatus $status): void;
```

```php
enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
```

New migration `add_status_to_arazzo_executions_table.php` (separate file, not editing doc 02's migration — keeps the two specs' implementation order decoupled): adds `status` (string, default `running`) and `completed_at` (nullable timestamp) to `arazzo_executions`. `DatabaseExecutionRegistry::complete()` does an `UPDATE ... SET status = ?, completed_at = now() WHERE id = ? AND status = 'running'` (guards against double-completion).

### Pending correlations (AsyncAPI suspension)

New table `arazzo_pending_correlations`: `correlation_id` (string, unique), `execution_id` (ulid, indexed), `step_id` (string), `channel_path` (string), `created_at`. New interface:

```php
interface PendingCorrelationRegistryInterface
{
    public function create(string $correlationId, string $executionId, string $stepId, string $channelPath): void;
    public function findByCorrelationId(string $correlationId): ?PendingCorrelation;
    public function consume(string $correlationId): void; // deletes/marks resolved
    public function existsForExecution(string $executionId): bool; // used by auto-completion check below
}
```

`DatabasePendingCorrelationRegistry` implements it. Unlike `DatabaseEventLedger`, write failures here **propagate** rather than being swallowed-and-logged: a silently-lost correlation write strands the execution waiting on a webhook that can never arrive, which is worse than a loud failure at suspend time.

## Components

### `StepProtocolExecutorInterface` — HTTP vs AsyncAPI dispatch

```php
interface StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool;
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome;
}
```

`StepExecutionOutcome` is a small value object: either `resolved(int $statusCode, array $outputs, array $responseBody)` or `suspended()`. `StepExecutionWorker` picks the first executor whose `supports()` returns true (registered as an ordered list in the service provider), instead of growing an `if ($step is asyncapi)` branch inline.

- **`HttpStepExecutor`** wraps today's `compileRequest` → `sendRequest` → `extractOutputs` → `evaluateSuccessCriteria` flow (moved out of `StepExecutionWorker::handle()` into this class, otherwise unchanged) and returns `resolved(...)`.
- **`AsyncApiStepExecutor`** resolves the step's `correlationId` expression against `$context`, resolves `channelPath`, writes a `PendingCorrelation` row via `PendingCorrelationRegistryInterface::create()`, and returns `suspended()`. `action: send` steps publish the message (via the existing `SourceResolver`/HTTP client stack against the AsyncAPI channel's binding) and return `resolved()` immediately — no criteria to evaluate, success is "the publish didn't throw."

`SourceType::Asyncapi` is added to the existing enum; `LaravelArazzoServiceProvider`'s source-parser map gains an `AsyncApiSourceParser` entry alongside the existing `OpenApiSourceParser`/`ArazzoSourceParser`, following the exact pattern already there.

### `StepOutcomeHandler` — retry / goto / end decision logic

New framework-agnostic service (`src/Execution/StepOutcomeHandler.php`), the single place that owns everything doc 03 is actually about. Called by `StepExecutionWorker` after a `resolved()` outcome (HTTP path, or a resumed AsyncAPI correlation), never for `suspended()` outcomes.

```php
public function handle(
    ArazzoDocument $document,
    Workflow $workflow,
    Step $step,
    WorkflowContext $context,
    string $executionId,
    bool $criteriaMet,
): void
```

Logic:

1. **Action list selection.** Per Arazzo's inheritance rule: use `$step->onSuccess`/`$step->onFailure` if non-empty, else fall back to `$workflow->successActions`/`$workflow->failureActions`. Pick the success or failure list based on `$criteriaMet`.
2. **Action matching.** Walk the list in order; each action carries its own `criteria` (`SuccessCriterion[]`) gating whether it applies (evaluated via the same criterion evaluator, against the step's response). First action whose criteria all pass wins. Empty list, or no action matches → **implicit default**: success → continue normally (step 5 below); failure → terminal failure (jump to End-Failure handling).
3. **`RetryAction`.** Let `attempts = $context->getStepAttempts($stepId)`. A config-level safety ceiling (`arazzo.max_retry_attempts`, default 10) applies regardless of the document's own `retryLimit` — this exists purely to bound runaway loops from a misauthored spec; it is not an Arazzo semantic. If `attempts >= min($retryLimit ?? PHP_INT_MAX, $ceiling)`: append `step.retry_exhausted` to the event ledger and re-run the match (step 2) excluding this action, i.e. fall through to the next `onFailure` entry. Otherwise: increment attempts, set status `Retrying`, resolve retry target (`$action->stepId`/`$action->workflowId`, defaulting to the current step/workflow when both are null), and re-dispatch via `QueueDriverInterface::dispatch($job, delaySeconds: $action->retryAfter ?? 0)`.
4. **`*GotoAction`.** Resolve target workflow: if `$action->workflowId` is null or equals the current workflow, target = current workflow; else look up `$action->workflowId` in `$document->workflows` — not found throws `GotoTargetNotFoundException` (surfaces as a failed queue job via Laravel's own `failed_jobs`, no bespoke handling needed). If `$action->stepId` is set, reset that step's status to `Pending` in the context (so it re-runs even if previously succeeded — enables loop-back patterns) and dispatch it directly, bypassing `DependencyAnalyzer`. If only `$action->workflowId` is set (no `stepId`), call `$context->withWorkflowId($targetWorkflowId)` then `Engine::evaluate($targetWorkflow, $context)`, letting normal dependency-driven choreography pick that workflow's entry steps. Cross-workflow goto only ever targets a workflow inside the *same* `ArazzoDocument` — jumping into a different document/definition entirely is out of scope (that's saga/doc-07 territory).
5. **`*EndAction` / implicit success continuation.** `End` (either kind) calls `ExecutionRegistryInterface::complete($executionId, Succeeded|Failed)`, appends `execution.succeeded`/`execution.failed`, and stops — no further dispatch. Implicit continuation (no action matched, criteria met) calls `Engine::evaluate($workflow, $context)` as today; if the resulting runnable-steps set is empty **and** `PendingCorrelationRegistryInterface::existsForExecution($executionId)` is false, the workflow has genuinely run out of work — mark the execution `Succeeded` automatically. If runnable steps are empty but a correlation is outstanding, do nothing (waiting on a webhook).

### `WebhookResumeController` — AsyncAPI resume entrypoint

New route, following the existing `packageBooted()` pattern:

```php
Route::post('/api/arazzo/webhooks/{correlationId}', [WebhookResumeController::class, 'resume'])->middleware('api');
```

Handler: look up the `PendingCorrelation`; 404 (no state mutation) if missing or already consumed. On a hit, it does **not** process synchronously in the HTTP request — it dispatches a `ResumeCorrelationJob` (real `ShouldQueue`, same wrapping pattern as `ExecuteStepJob` below) carrying the correlation id and raw payload, and returns `202 Accepted` immediately. This keeps webhook response latency low and makes the inbound call safely retriable by the sender (idempotent — a second resume hit on an already-consumed correlation is just a 404, not a re-processed step).

`ResumeCorrelationJob::handle()`: loads the `PendingCorrelation`, loads execution state via `StateStoreInterface::load($executionId)`, reconstructs `Step`/`Workflow`/`ArazzoDocument` via `DefinitionRegistryInterface`, merges the inbound payload into `steps[$stepId]['response']['body']`, calls `PendingCorrelationRegistryInterface::consume()`, evaluates success criteria the same way `HttpStepExecutor` does, and calls `StepOutcomeHandler::handle()` — the exact same decision logic the HTTP path uses, so retry/goto/end semantics aren't duplicated for the async-receive case.

No signature verification / auth on the webhook route in this spec — `correlationId` unguessability (a ULID-class random token) is the only protection. Flagging this explicitly: if the target AsyncAPI channel needs HMAC/signed-webhook verification, that's a follow-up, not blocking this design.

### Diamond / fan-in fix

Two changes to `StepExecutionWorker::handle()`, layered on top of doc 02's target rewrite:

1. Lock key changes from `workflow_lock_{definitionId}` to `execution_lock_{executionId}` — the current key over-serializes unrelated concurrent executions of the same definition and, more importantly, is the wrong granularity for the actual race (which is between sibling steps *within one execution*).
2. Inside the lock, after the idempotency check passes, **reload** persisted state via `StateStoreInterface::load($executionId)` and reconcile it with the job's own context (union of both `steps` maps — the reload is authoritative for any step this worker didn't just execute) before calling `Engine::evaluate()`/`StepOutcomeHandler::handle()`. This closes the lost-update race: whichever sibling (B or C) finishes second is the only one who ever observes both dependencies satisfied in a consistent read, so the fan-in step (D) dispatches exactly once. No separate "already dispatched" ledger is needed — correct lock scope plus a fresh read is sufficient.

### `ExecuteStepJob` → real `ShouldQueue`

New `src/Laravel/Jobs/RunExecuteStepJob.php`:

```php
class RunExecuteStepJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(private ExecuteStepJob $inner) {}

    public function handle(StepExecutionWorker $worker): void
    {
        $worker->handle($this->inner);
    }
}
```

`LaravelQueueDriver::dispatch()` wraps every plain `ExecuteStepJob`/resume-job payload in this class before calling `Queue::push()`/`Queue::later()`. Keeps `src/Execution/` free of `Illuminate\*` imports (existing project constraint) while making delayed retry dispatch actually work against a real queue connection — today it silently doesn't.

### Service provider wiring

`packageRegistered()` gains bindings this doc 02 plan explicitly left out as "item 03's territory": `LockManagerInterface` → `LaravelRedisLockManager`, `QueueDriverInterface` → `LaravelQueueDriver`, `Engine::class`, plus this spec's own `StepOutcomeHandler`, `StepProtocolExecutorInterface[]` (ordered `[HttpStepExecutor, AsyncApiStepExecutor]`), `PendingCorrelationRegistryInterface` → `DatabasePendingCorrelationRegistry`. New config keys: `arazzo.max_retry_attempts` (default `10`), `arazzo.pending_correlations_table` (default `arazzo_pending_correlations`). `packageBooted()` gains the webhook route above.

## Error handling summary

| Situation | Behavior |
|---|---|
| Webhook hit, unknown/consumed correlation | `404`, no state mutation |
| `goto` targets unknown `workflowId`/`stepId` | Throws `GotoTargetNotFoundException`; job fails into Laravel's `failed_jobs` — no bespoke retry-of-retry logic, that's infra-layer, separate from Arazzo document-level retry |
| Retry ceiling exceeded (config safety net, independent of document's `retryLimit`) | Treated as retry-exhausted: falls to next `onFailure` action or terminal failure; warning-level log |
| `PendingCorrelation` write fails | Propagates (unlike `EventLedger`, which swallows) — silently losing it stranded the execution forever |
| `EventLedger`/`ExecutionRegistry` write fails mid-flow | Existing doc-02 swallow-and-log behavior for the ledger; `complete()` failures propagate (a failed status transition should not be silently lost) |

## Testing strategy

- **Unit — `StepOutcomeHandler`:** full decision-table coverage (success/failure × retry/goto/end/no-match × criteria-match/no-match), fake `QueueDriverInterface`/`ExecutionRegistryInterface`/`EventLedgerInterface`.
- **Unit — retry counter:** attempts increment across simulated redispatch cycles; ceiling enforcement independent of document `retryLimit`.
- **Unit — goto:** same-workflow stepId jump (including loop-back onto an already-`Succeeded` step), cross-workflow jump via a multi-workflow `ArazzoDocument` fixture, unknown-target exception.
- **Feature — diamond DAG regression:** two `StepExecutionWorker::handle()` calls simulating B/C finishing concurrently against a shared real `StateStore` + real lock manager; assert D dispatches exactly once and final persisted state has all of A/B/C/D.
- **Feature — AsyncAPI suspend/resume round-trip:** using `tests/fixtures/parser/arazzo-1.1-cross-protocol-saga.yaml` (referenced by the roadmap doc, does not exist yet — created as part of this spec's implementation plan). Dispatch a step, assert `Suspended` status + a `PendingCorrelation` row, `POST` the webhook route, assert resumed context and eventual execution completion.
- **Feature — real queue round-trip:** `RunExecuteStepJob` actually serializes/deserializes through a real (sync-driver) Laravel queue connection, directly covering the "plain object" bug class.
