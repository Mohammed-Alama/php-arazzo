# Design — #61: Migrate cli + laravel to consume runner/document/expression via public faces

Date: 2026-09-07
Issue: #61

## Context

The "one public face per package" effort has migrated the expression package
(#59) and the runner internals (#60) onto their public faces. The runner now
ships a public `RunnerFacadeInterface` (sync `run()` / `execute()`) delivered
in #58, backed by an internal `ExecutionGraphFactory`.

Two consumers still reach into runner internals:

- **cli** `RunCommand` reassembles the whole sync graph (`WorkflowExecutor`,
  `StepExecutor`, `DefaultOpenApiExecutor`, `ExecutionExpressionResolver`,
  `StepOutputExtractor`, `ResponseSchemaValidator`, `WorkflowEngine`) plus a
  Guzzle client/factory just to run one workflow.
- **laravel** `ExecutionBindings` wires the *entire* async/queue execution
  surface from internal concrete types — `StepExecutionWorker`,
  `CorrelationResumer`, `StepOutcomeHandler`, `RunPersistence`,
  `RunControlFlow`, `IdempotencyKeyInjector`, `WorkflowEngine`, and the three
  protocol executors — alongside contracts interfaces.

Third-party types are already sealed behind the document/expression faces;
laravel's `Psr18HttpClient` implements the runner's tiny async-transport SPI
(`HttpClientInterface`) and is a first-class framework adapter, not a leak.

## Goals

- cli and laravel resolve the runner through its public face and value types
  only — no runner internal concrete imports.
- The runner owns the execution composition root (a public builder), sealing
  how executors, workers, resumers, persistence and control-flow are assembled.
- Behaviour, configuration knobs and tests are unchanged (full gate green).

## Non-goals

- No change to `RunnerFacadeInterface`'s existing `run()`/`execute()` contract.
- No changes to the `Contracts` SPI interfaces beyond what laravel already
  consumes.
- No third-party type may appear in a package's public signature (later #62
  scope — confirmed here that none is introduced).

## Design

### Public API additions (runner)

New root-namespace public types, adjacent to `RunnerFacadeInterface`:

1. `RunnerGraphBuilderInterface` — the composition root. One entry point:

   ```php
   public function buildAsync(AsyncGraphSeams $seams): AsyncExecutionGraph;
   ```

   The builder is constructed with the two public faces it composes:
   `DocumentInterface` and `ExpressionEngineInterface`.

2. `AsyncGraphSeams` — public value type (named-arg constructor) carrying
   exclusively framework-owned or config inputs:

   | field | type |
   |---|---|
   | `stateStore` | `Contracts\Interfaces\StateStoreInterface` |
   | `queueDriver` | `Contracts\Interfaces\QueueDriverInterface` |
   | `eventLedger` | `Runner\Events\Interfaces\EventLedgerInterface` |
   | `executionRegistry` | `Contracts\Interfaces\ExecutionRegistryInterface` |
   | `pendingCorrelationRegistry` | `Contracts\Interfaces\PendingCorrelationRegistryInterface` |
   | `definitionRegistry` | `Contracts\Interfaces\DefinitionRegistryInterface` |
   | `lockManager` | `Contracts\Interfaces\LockManagerInterface` |
   | `httpClient` | `Runner\Infrastructure\Interfaces\HttpClientInterface` (async transport) |
   | `requestFactory` | `Psr\Http\Message\RequestFactoryInterface` |
   | `logger` | `Psr\Log\LoggerInterface` |
   | `idempotencyEnabled` / `idempotencyHeader` | `bool` / `string` |
   | `strictValidation` | `bool` |
   | `retryCeiling` / `retryBackoffMultiplier` | `int` / `float` |
   | `stateTtlSeconds` | `int` |

3. `AsyncExecutionGraph` — public, readonly value object exposing the wired
   graph components: `workflowExecutor()`, `worker()`, `resumer()`,
   `protocolExecutors(): list<StepProtocolExecutorInterface>`.

Implementation stays internal. The existing `ExecutionGraphFactory`
(`createWorkflowExecutor`, used by `RunnerFacade`) remains the sync
composition root; an internal sibling assembles the async graph
(`buildAsync`) — sharing a single `WorkflowExecutor` instance across
`SubWorkflowStepExecutor`, `SubWorkflowInvoker` and the worker's protocol
executor list, and constructing `DefaultOpenApiExecutor` from the PSR
client/factory seam where the sync path defaults to Guzzle.

Runner's public surface therefore stays: public face interfaces + public
value types (the shape #63 validates against).

### cli (`RunCommand`)

Replace the manual assembly (currently ~20 lines + 6 runner-internal imports)
with the public face:

```php
$documents = new Document($client, $factory, $this->registry);
$engine    = new ExpressionEngine();
$runner    = new RunnerFacade($documents, $engine, $this->httpClient);

$result = $runner->execute($document, $workflow->workflowId, $inputs);
```

- Render the existing output (status line, per-step `✔`/`✘` lines, outputs)
  from the returned shape (`steps` map + `outputs` + `status`).
- Keep the unknown-workflow pre-check and first-workflow default; pass the
  resolved id to the facade.
- `RunnerFacade`'s sync graph already defaults its HTTP client, but `Document`
  construction still needs the explicit client/factory that source fetching
  requires — Guzzle wiring stays only there.
- Exit code continues from `status === 'succeeded'`.

### laravel (`ExecutionBindings`)

- Bind `RunnerGraphBuilderInterface` in `FacadeBindings` next to
  `RunnerFacadeInterface` (closure is lazy; ordering vs. `ExecutionBindings`
  is irrelevant because resolution happens at first `make()`).
- `ExecutionBindings::register` now:
  1. builds `AsyncGraphSeams` from `app->make(Contracts\Interfaces\*)` +
     laravel-owned bits (`Psr18HttpClient` via `HttpClientInterface`,
     `ConfigValue` config knobs),
  2. binds `AsyncExecutionGraph` as singleton,
  3. re-exports singleton aliases the jobs/webhooks resolve today
     (`StepExecutionWorker`, `CorrelationResumer`, `WorkflowExecutor`).
- Remaining laravel imports: `Contracts` interfaces, runner public types
  (`RunnerGraphBuilderInterface`, `AsyncExecutionGraph`, `AsyncGraphSeams`),
  laravel's own classes, PSR interfaces, and the runner async-transport SPI
  (`HttpClientInterface` — implemented by `Psr18HttpClient`). **No runner
  internal concrete imports.**
- `IdempotencyKeyInjector` and `SubWorkflowStepExecutor` leave the bindings:
  the former is composed inside the graph, the latter is auto-resolved by the
  worker's executor array against the shared `WorkflowExecutor`.

## Data flow

Unchanged at runtime. Build time is the only new path: `AsyncGraphSeams`
→ `RunnerGraphBuilder::buildAsync()` → `AsyncExecutionGraph`, bound once by
laravel; jobs/webhooks pull the same singletons as today. Build failures
(config errors) surface at boot/resolution exactly like today's bindings.

## Testing

- **runner**: unit tests for the async builder — wiring completeness, shared
  `WorkflowExecutor` identity across `subWorkflowExecutor`/`invoker`/worker,
  protocol-executor order, seam pass-through.
- **laravel**: a `LaravelFaceSeamTest` mirroring the runner's seam guard
  (no `Alama\Arazzo\Runner\**` internal concrete imports in
  `packages/laravel/src`, an allow-list for the async-transport SPI and the
  new public types); existing suites stay green.
- **cli**: `RunCommand` test asserts `execute()` output shape (status/steps/
  outputs) and unchanged exit code; no runner-internal imports remain.
- **gate**: `make verify` (docs → pint → phpstan → pest) + regenerated
  `docs/generated`.

## Open items

- None. (Verified: `IdempotencyKeyInjector`/`SubWorkflowStepExecutor` are
  referenced nowhere outside `ExecutionBindings`; provider ordering is
  lazy-resolve safe; `HttpClientInterface` is already allowed adapter SPI.)