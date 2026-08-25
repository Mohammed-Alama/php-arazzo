# Runner Module Modularization

## Goal

Decompose the `Runner` module (113 files, 7.6K LOC, 44% of project churn) into cohesive, independently testable components with clear boundaries. Reduce coupling to `Spec` (115 refs) and `Expression` (15 refs), improve async/sync execution parity, and strengthen observability via OpenTelemetry.

## Current Pain Points

| Metric | Value | Signal |
|--------|-------|--------|
| Files | 113 | Monolithic |
| LOC | 7,644 | Largest module |
| Churn share | 44% | Highest edit pressure |
| Touches/KLOC | 7.2 | Above average |
| Fan-out | 5 | Depends on Expression, Resolver, Spec, Support, Validator |
| Fan-in | 9 | Console, Laravel/Bindings, Laravel/Persistence, Laravel/Queue, Laravel/State, Parser, Renderer, Support, Validator |

## Target Architecture

```
Runner/
├── Execution/           # Pure state machine (WorkflowEngine, Transition, ExecutionState)
├── Contracts/           # Interfaces (ExecutionRegistry, QueueDriver, LockManager, EventLedger, StateStore, StepProtocolExecutor)
├── Adapter/
│   ├── Sync/            # In-process execution (WorkflowExecutor)
│   ├── Async/           # Queue-based execution (StepExecutionWorker → StepExecutor, TransitionDispatcher, StateReconciler)
│   ├── CLI/             # File-based persistence + OTel logging for CLI runs
│   └── Laravel/         # Framework bindings (already in packages/laravel)
├── Policy/              # RetryPolicy, LockStrategy, BackoffCalculator
├── Telemetry/           # OpenTelemetry integration (traces, metrics, logs)
├── Protocol/            # ProtocolExecutorRegistry, HttpExecutor, AsyncApiExecutor, SubWorkflowExecutor
└── State/               # ExecutionContext (unified), StepResult, WorkflowOutput
```

## Core Responsibilities (Unchanged)

- **WorkflowEngine**: Pure transition logic (already clean, 293 lines)
- **ExecutionState/WorkflowContext**: Unified into `ExecutionContext` with clear read/write phases
- **StepProtocolExecutorInterface**: Single protocol abstraction for HTTP, AsyncAPI, sub-workflows
- **Contracts**: Adapter boundaries (QueueDriver, LockManager, StateStore, EventLedger, ExecutionRegistry)

## Key Changes

### 1. ExecutionContext (Unifies ExecutionState + WorkflowContext)

```php
final class ExecutionContext
{
    // Read phase (immutable view for evaluators)
    public function getExecutionId(): string;
    public function getDefinitionId(): string;
    public function getWorkflowId(): string;
    public function getCurrentStepId(): ?string;
    public function getInputs(): array;
    public function getComponents(): Components;
    public function getStepResults(): array;           // stepId => StepResult
    public function getStepAttempts(string $stepId): int;
    public function getBudget(): Budget;
    public function getWorkflowCallStack(): array;
    public function getVariables(): VariableScope;      // for expression evaluation
    
    // Write phase (builder pattern, produces new instance)
    public function withStepResult(string $stepId, StepResult $result): self;
    public function withStepAttempt(string $stepId): self;
    public function spendStep(): self;
    public function withWorkflow(string $workflowId): self;
    public function withCurrentStep(string $stepId): self;
    public function withInputs(array $inputs): self;
    public function withError(ErrorEntry $error): self;
    public function restoreBudget(int $spent, array $callStack): self;
}
```

### 2. StepExecutionWorker → Composed Handlers

| Handler | Responsibility |
|---------|----------------|
| `StateReconciler` | Load persisted state, merge with job context, handle `receive` suspension |
| `PreflightGuard` | Run preflight validation once per execution |
| `ProtocolExecutorRegistry` | Map step action/type → `StepProtocolExecutorInterface` |
| `StepExecutor` | Execute single step via protocol executor, return `StepExecutionOutcome` |
| `CriteriaEvaluator` | Evaluate success criteria using shared expression resolver |
| `TransitionDispatcher` | Apply `WorkflowEngine` transition, persist state, emit events, enqueue next step |
| `EventEmitter` | Domain events (StepStarted, StepExecuted, StepFailed, RunCompleted, RunFailed, CorrelationPending) |

### 3. ProtocolExecutorRegistry

```php
interface ProtocolExecutorRegistryInterface
{
    public function register(string $action, string $protocol, StepProtocolExecutorInterface $executor): void;
    public function resolve(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface;
    public function getSupportedProtocols(): array;  // for diagnostics
}
```

### 4. RetryPolicy (Extracted from WorkflowEngine)

```php
final class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 10,
        public float $backoffMultiplier = 1.0,
        public ?BackoffCalculator $calculator = null,
    ) {}
    
    public function calculateDelay(RetryAction $action, Step $step, WorkflowContext $context, int $upcomingAttempt): int;
    public function isExhausted(int $attemptsSoFar, ?int $limit): bool;
}
```

### 5. LockStrategyInterface

```php
interface LockStrategyInterface
{
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed;
    public function tryAcquire(string $key, int $ttlSeconds): bool;
    public function release(string $key): void;
}

// Implementations: PessimisticLock (current), OptimisticLock, DistributedLock (Redis), FileLock (CLI), NullLock (testing)
```

### 6. OpenTelemetry Integration (Replaces custom TelemetryCollector)

Use **OpenTelemetry PHP SDK** directly — no custom interface. Emit:

- **Spans**: `workflow.execute`, `step.execute`, `step.retry`, `step.suspend`, `subworkflow.invoke`, `transition.apply`
- **Attributes**: `execution.id`, `workflow.id`, `step.id`, `attempt`, `criteria.met`, `status`, `error.category`
- **Metrics**: `workflow.duration`, `step.duration`, `step.attempts`, `workflow.completed`, `workflow.failed`, `subworkflow.depth`
- **Logs**: Structured JSON logs via OTel log bridge (file stdout for CLI, Loki/Elastic for prod)

```php
// Usage pattern in handlers
$tracer = \OpenTelemetry\API\Globals::tracerProvider()->get('arazzo.runner');
$span = $tracer->spanBuilder('step.execute')
    ->setAttribute('execution.id', $executionId)
    ->setAttribute('workflow.id', $workflowId)
    ->setAttribute('step.id', $stepId)
    ->setAttribute('attempt', $attempt)
    ->startSpan();

try {
    $outcome = $executor->execute(...);
    $span->setAttribute('criteria.met', $criteriaMet);
    $span->setStatus(\OpenTelemetry\API\Trace\SpanStatus::STATUS_OK);
} catch (\Throwable $e) {
    $span->recordException($e);
    $span->setStatus(\OpenTelemetry\API\Trace\SpanStatus::STATUS_ERROR);
    throw $e;
} finally {
    $span->end();
}
```

**CLI Adapter**: Configure OTel to export to file (JSON lines) or stdout. No DB required.

### 7. State Persistence Abstraction

```php
interface StateStoreInterface
{
    public function save(string $executionId, ExecutionContext $context, int $ttlSeconds): void;
    public function load(string $executionId): ?ExecutionContext;
    public function delete(string $executionId): void;
}

// Implementations:
// - RedisStateStore (Laravel/production async)
// - DatabaseStateStore (Laravel/persistent)
// - FileStateStore (CLI/sync - stores JSON in ./storage/executions/{id}.json)
// - InMemoryStateStore (testing)
```

### 8. SubWorkflowExecutor (High-frequency, first-class protocol)

```php
final class SubWorkflowExecutor implements StepProtocolExecutorInterface
{
    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->action === 'invoke' || $step->type === 'workflow';
    }
    
    public function execute(Step $step, ExecutionContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        // Create child execution context with parent reference
        // Run WorkflowEngine loop for sub-workflow
        // Return outcome with sub-workflow outputs
    }
}
```

## Adapters (Both Sync & Async Equally Important)

### Sync Adapter (`WorkflowExecutor`)
- Runs `WorkflowEngine` in loop until terminal
- Uses `ProtocolExecutorRegistry` for step execution (includes `SubWorkflowExecutor`)
- Direct state updates via `StateStoreInterface` (FileStateStore for CLI)
- OTel spans for full workflow trace

### Async Adapter (`StepExecutionWorker` + handlers)
- Single step per job
- Lock → reconcile → execute → evaluate → transition → persist → enqueue
- Each handler independently testable
- OTel spans per step, linked via trace context propagation

### CLI Adapter (packages/core/bin/arazzo or similar)
- Uses `WorkflowExecutor` (sync) with `FileStateStore`
- OTel configured for file/stdout export
- Command: `arazzo run <workflow-file> --inputs=<json> --output=<file>`

### Laravel Adapter (packages/laravel)
- Binds contracts to Laravel implementations
- Zero decision logic
- OTel auto-configured via Laravel Octane/OTel package

## Compatibility

- **Breaking changes allowed** — internal refactor, public API (`WorkflowEngine`, `Transition`, `ExecutionResult`) preserved
- Existing tests must pass without modification (behavioral parity)
- New tests for each extracted component

## Acceptance Criteria

1. **Modularity**: Each handler/component < 150 lines, single responsibility
2. **Testability**: Each handler testable in isolation with mocks (no full integration setup)
3. **Parity**: Sync and async adapters produce identical `ExecutionResult` for same document
4. **Observability**: OpenTelemetry captures all execution transitions, errors, timing, sub-workflow nesting
5. **CLI Support**: File-based persistence + OTel file export works without DB/Redis
6. **Sub-workflow Performance**: High-frequency invoke adds < 5ms overhead vs inline
7. **Churn reduction**: Runner module churn drops below 20% of project total
8. **Coupling**: Fan-out from Runner ≤ 3 (Expression, Spec, Contracts only)
