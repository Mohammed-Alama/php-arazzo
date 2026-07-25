# PSR-14 Event Dispatcher Wiring — Design

Stub: [`docs/superpowers/roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md`](../roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md)
Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Enables: pro-observability, `bridge-28` (Horizon/Telescope), `tenant-09` (context bridges), `health-23` (error triage).

## Problem

The engine has two observability paths today:

- **`EventLedgerInterface::append(executionId, eventType, payload)`** — durable, DB-backed
  via `DatabaseEventLedger`. Emits string-keyed events (`step.executed`, `step.suspended`,
  `step.resumed`, `execution.definition_missing`, `execution.workflow_missing`,
  `execution.step_missing`, `execution.state_missing`).
- **`ExecutionLoggerInterface::logStep{Started,Completed,Failed}`** — trivial log-only,
  used only by sync `WorkflowExecutor`.

Neither reaches arbitrary listeners; no PSR-14 dispatcher exists. This blocks:
framework-agnostic listeners, Symfony EventDispatcher consumers, pro-observability
packages that hook lifecycle without touching engine code, and any external audit /
telemetry integration.

A legacy `Alama\LaravelArazzo\Execution\Events\StepExecuted` DTO exists but is not
dispatched anywhere in the current flow.

## Approach

Add a PSR-14 event bus that **coexists** with the existing ledger. Broadcast is a
different concern from durable persistence. A built-in `LedgerAppendingListener` bridges
the bus back to `EventLedgerInterface`, so ledger output stays byte-identical.

Nine canonical typed events under `Alama\LaravelArazzo\Events\*`. Two dispatcher
implementations shipped in-package: `SimpleEventDispatcher` (in-memory PSR-14 listener
registry) and `NullEventDispatcher` (no-op). Laravel adapter
(`IlluminatePsrEventDispatcher`) shipped but not bound by default — Laravel consumers
opt in.

Six execution classes gain `Psr\EventDispatcher\EventDispatcherInterface` via constructor
injection; each dispatches only the events it owns.

## Framework Boundary (matters for Plan A extraction)

Plan A (`docs/superpowers/plans/2026-07-25-plan-a-core-extraction.md`) will move the
engine into `packages/core/` (namespace `Alama\Arazzo\*`) and the Laravel bridge into
`packages/laravel/` (namespace `Alama\Arazzo\Laravel\*`). This design keeps that boundary
clean:

- **Core-agnostic (today: `Alama\LaravelArazzo\`; post-extraction: `Alama\Arazzo\`)**:
  event DTOs, `SimpleEventDispatcher`, `NullEventDispatcher`, `LedgerAppendingListener`.
  Zero Illuminate imports.
- **Laravel-only (today: `Alama\LaravelArazzo\Laravel\`; post-extraction:
  `Alama\Arazzo\Laravel\`)**: `IlluminatePsrEventDispatcher` adapter,
  `LaravelArazzoServiceProvider` bindings.
- **Composer**: `psr/event-dispatcher ^1.0` required on the framework-agnostic side.
  Laravel bridge inherits transitively.

Every one of the six injection sites uses `Psr\EventDispatcher\EventDispatcherInterface`,
never a concrete Laravel type. Post-extraction the move is a namespace rewrite; no
architectural change.

## Architecture

Layer additions:

- **`src/Events/`** (new): 9 event DTOs.
- **`src/Events/Dispatcher/`** (new): `SimpleEventDispatcher`, `NullEventDispatcher`.
- **`src/Events/Listener/`** (new): `LedgerAppendingListener` (bridges bus → existing
  `EventLedgerInterface`).
- **`src/Laravel/Events/`** (new): `IlluminatePsrEventDispatcher` (opt-in adapter).
- **`src/Execution/`** (modified): six classes gain dispatcher param.
- **`src/LaravelArazzoServiceProvider.php`** (modified): default binding =
  `SimpleEventDispatcher`; auto-wire `LedgerAppendingListener` when both dispatcher and
  `EventLedgerInterface` resolve.

Existing `EventLedgerInterface` untouched. Existing `ExecutionLoggerInterface` untouched.
Existing 6 non-catalog ledger strings (`step.suspended`, `execution.definition_missing`,
`execution.workflow_missing`, `execution.step_missing`, `execution.state_missing`,
`step.resumed`) continue to be emitted directly via `EventLedger::append` — they don't
have typed classes in the v1 catalog.

## Event Catalog

Nine `final readonly class` DTOs under `Alama\LaravelArazzo\Events\`. Every event carries
`executionId`, most carry `workflowId`, all carry `\DateTimeImmutable $at`.

```php
namespace Alama\LaravelArazzo\Events;

final readonly class RunStarted {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $definitionId,
        /** @var array<string, mixed> */ public array $inputs,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class RunCompleted {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        /** @var array<string, mixed> */ public array $outputs,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class RunFailed {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public \Throwable $cause,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class StepStarted {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class StepExecuted {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $statusCode,
        /** @var array<string, mixed> */ public array $outputs,
        public bool $criteriaMet,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class StepRetried {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public ?\Throwable $lastError,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class StepFailed {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public \Throwable $cause,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class CorrelationPending {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public string $channelPath,
        public \DateTimeImmutable $at,
    ) {}
}

final readonly class CorrelationResumed {
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public \DateTimeImmutable $at,
    ) {}
}
```

**Legacy `Alama\LaravelArazzo\Execution\Events\StepExecuted`** kept in place, annotated
`@deprecated`. Not removed to avoid breaking any external listener that may exist. Marked
for removal in a future major.

## Dispatchers

**`SimpleEventDispatcher`** (`src/Events/Dispatcher/SimpleEventDispatcher.php`):

```php
namespace Alama\LaravelArazzo\Events\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

final class SimpleEventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    /** @var array<class-string, list<callable>> */
    private array $listeners = [];

    /**
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }
        return $event;
    }

    /** @return iterable<callable> */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $class => $callables) {
            if ($event instanceof $class) {
                yield from $callables;
            }
        }
    }
}
```

Supports subclass matching. No listener priority (YAGNI).

**`NullEventDispatcher`** (`src/Events/Dispatcher/NullEventDispatcher.php`):

```php
namespace Alama\LaravelArazzo\Events\Dispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        return $event;
    }
}
```

## Ledger Bridge

**`LedgerAppendingListener`** (`src/Events/Listener/LedgerAppendingListener.php`) maps
each of the 9 typed events to the existing ledger string schema:

| Event | Ledger string | Payload keys |
|---|---|---|
| `RunStarted` | `run.started` | `workflowId`, `definitionId`, `inputs` |
| `RunCompleted` | `run.completed` | `workflowId`, `outputs` |
| `RunFailed` | `run.failed` | `workflowId`, `error` (`{class, message}`) |
| `StepStarted` | `step.started` | `stepId`, `attempt` |
| `StepExecuted` | `step.executed` | `stepId`, `statusCode`, `outputs`, `criteriaMet` |
| `StepRetried` | `step.retried` | `stepId`, `attempt`, `lastError` (`{class, message}` or `null`) |
| `StepFailed` | `step.failed` | `stepId`, `error` (`{class, message}`) |
| `CorrelationPending` | `correlation.pending` | `stepId`, `correlationId`, `channelPath` |
| `CorrelationResumed` | `correlation.resumed` | `stepId`, `correlationId` |

```php
namespace Alama\LaravelArazzo\Events\Listener;

use Alama\LaravelArazzo\Events\{
    RunStarted, RunCompleted, RunFailed,
    StepStarted, StepExecuted, StepRetried, StepFailed,
    CorrelationPending, CorrelationResumed,
};
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;

final class LedgerAppendingListener
{
    public function __construct(private EventLedgerInterface $ledger) {}

    public function __invoke(object $event): void
    {
        [$type, $payload] = match (true) {
            $event instanceof RunStarted         => ['run.started',          ['workflowId' => $event->workflowId, 'definitionId' => $event->definitionId, 'inputs' => $event->inputs]],
            $event instanceof RunCompleted       => ['run.completed',        ['workflowId' => $event->workflowId, 'outputs' => $event->outputs]],
            $event instanceof RunFailed          => ['run.failed',           ['workflowId' => $event->workflowId, 'error' => ['class' => $event->cause::class, 'message' => $event->cause->getMessage()]]],
            $event instanceof StepStarted        => ['step.started',         ['stepId' => $event->stepId, 'attempt' => $event->attempt]],
            $event instanceof StepExecuted       => ['step.executed',        ['stepId' => $event->stepId, 'statusCode' => $event->statusCode, 'outputs' => $event->outputs, 'criteriaMet' => $event->criteriaMet]],
            $event instanceof StepRetried        => ['step.retried',         ['stepId' => $event->stepId, 'attempt' => $event->attempt, 'lastError' => $event->lastError !== null ? ['class' => $event->lastError::class, 'message' => $event->lastError->getMessage()] : null]],
            $event instanceof StepFailed         => ['step.failed',          ['stepId' => $event->stepId, 'error' => ['class' => $event->cause::class, 'message' => $event->cause->getMessage()]]],
            $event instanceof CorrelationPending => ['correlation.pending',  ['stepId' => $event->stepId, 'correlationId' => $event->correlationId, 'channelPath' => $event->channelPath]],
            $event instanceof CorrelationResumed => ['correlation.resumed',  ['stepId' => $event->stepId, 'correlationId' => $event->correlationId]],
            default                              => [null, null],
        };

        if ($type !== null) {
            $this->ledger->append($event->executionId, $type, $payload);
        }
    }

    public static function registerAll(SimpleEventDispatcher $dispatcher, EventLedgerInterface $ledger): void
    {
        $listener = new self($ledger);
        foreach ([
            RunStarted::class, RunCompleted::class, RunFailed::class,
            StepStarted::class, StepExecuted::class, StepRetried::class, StepFailed::class,
            CorrelationPending::class, CorrelationResumed::class,
        ] as $eventClass) {
            $dispatcher->subscribe($eventClass, $listener);
        }
    }
}
```

The 6 non-catalog ledger strings (`step.suspended`, `execution.definition_missing`,
`execution.workflow_missing`, `execution.step_missing`, `execution.state_missing`,
`step.resumed`) remain emitted directly by `StepExecutionWorker` / `CorrelationResumer`
via `EventLedger::append`.

## Laravel Adapter

**`IlluminatePsrEventDispatcher`** (today: `src/Laravel/Events/`; post-extraction:
`packages/laravel/src/Events/`):

```php
namespace Alama\LaravelArazzo\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

final class IlluminatePsrEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private IlluminateDispatcher $dispatcher) {}

    public function dispatch(object $event): object
    {
        $this->dispatcher->dispatch($event);
        return $event;
    }
}
```

Illuminate's `Dispatcher::dispatch(object)` broadcasts by class name. Native. Zero
adapter magic beyond this shim.

**Not bound by default.** Consumers opt in with a single line in their `AppServiceProvider`:

```php
$this->app->bind(
    \Psr\EventDispatcher\EventDispatcherInterface::class,
    \Alama\LaravelArazzo\Laravel\Events\IlluminatePsrEventDispatcher::class,
);
```

## Injection + Dispatch Sites

Six classes gain `Psr\EventDispatcher\EventDispatcherInterface $events` via constructor
injection. Where the constructor already has optional trailing params, the events param
slots in as `?EventDispatcherInterface $events = null` defaulting to
`NullEventDispatcher` when `null` — keeps existing test setups compiling.

| Class | Method | Events | When |
|---|---|---|---|
| `WorkflowExecutor` | `execute()` | `RunStarted` | before step loop |
| `WorkflowExecutor` | `execute()` | `RunCompleted` | after successful loop |
| `WorkflowExecutor` | `execute()` | `RunFailed` | catch block wrapping loop |
| `WorkflowExecutor` | `execute()` | `StepStarted` | before each `stepExecutor->execute()` |
| `WorkflowExecutor` | `execute()` | `StepExecuted` | after successful step |
| `WorkflowExecutor` | `execute()` | `StepFailed` | after failed step |
| `Engine` | `evaluate()` | `RunStarted` | first `evaluate()` call for an `executionId` (guard via `hasStarted($executionId)` set on Engine) |
| `StepExecutionWorker` | `handle()` | `StepStarted` | at top of lock closure |
| `StepExecutionWorker` | `handle()` | `StepExecuted` | after successful protocol executor run |
| `StepExecutionWorker` | `handle()` | `CorrelationPending` | in `$outcome->suspended` branch (`action === 'receive'`) |
| `StepExecutionWorker` | `handle()` | `StepFailed` | catch block |
| `StepExecutor` | `execute()` | (none new) | injected for future retry-emitter hook. Parent `WorkflowExecutor` owns surrounding events. |
| `StepOutcomeHandler` | `handle()` | `StepRetried` | `RetryAction` triggers a retry job |
| `StepOutcomeHandler` | `handle()` | `RunCompleted` | success-end action terminates async run |
| `StepOutcomeHandler` | `handle()` | `RunFailed` | failure-end action |
| `CorrelationResumer` | `resume()` | `CorrelationResumed` | after `pendingCorrelations->consume()` |

**Overlap policy:** sync path (`WorkflowExecutor`) fires
`RunStarted/StepStarted/StepExecuted/RunCompleted`. Async path (`Engine` +
`StepExecutionWorker` + `StepOutcomeHandler`) fires the same set, keyed on
`executionId`. Consumers wanting a single unified stream subscribe once; they get events
from whichever path is active. A given `executionId` is either sync or async — no
cross-path duplication.

**Existing `EventLedger::append` calls:** for events in the 9-catalog, remove direct
`->append(...)` calls (they get routed through `LedgerAppendingListener` instead). For
the 6 non-catalog events, keep direct `->append(...)`. Net ledger output byte-identical.

**Retry counter semantics:** `StepStarted::$attempt` starts at `1`. `StepRetried::$attempt`
is the attempt number that just failed and will be retried. Subsequent `StepStarted::$attempt`
= previous `StepRetried::$attempt + 1`. `StepOutcomeHandler` reads the current retry
count from `WorkflowContext` (exact accessor name to verify during implementation).

**Timestamp source:** plain `new \DateTimeImmutable()` per YAGNI. Tests assert on time
proximity, not equality. If a `psr/clock` interface enters the codebase later, refactor
to that.

## Service Provider

`LaravelArazzoServiceProvider` (today: root; post-extraction: `packages/laravel/`):

```php
$this->app->bind(
    \Psr\EventDispatcher\EventDispatcherInterface::class,
    fn ($app) => $app->make(\Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher::class),
);

$this->app->singleton(
    \Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher::class,
    function ($app) {
        $dispatcher = new \Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher();
        if ($app->bound(\Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface::class)) {
            \Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener::registerAll(
                $dispatcher,
                $app->make(\Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface::class),
            );
        }
        return $dispatcher;
    },
);
```

`IlluminatePsrEventDispatcher` binding **not registered by default** (per Q4).
Documented recipe in CHANGELOG so Laravel consumers can opt in.

Consumers not touching the container get `SimpleEventDispatcher` — events dispatch to
whoever subscribed. `LedgerAppendingListener` auto-wires whenever `EventLedgerInterface`
is container-bound (default in Laravel setup).

## Composer

Add to `require` (framework-agnostic side; slotted for `packages/core/composer.json`
post-extraction):

```json
"psr/event-dispatcher": "^1.0"
```

Laravel bridge inherits transitively.

## Testing

**Fixtures:** none needed — event tests use synthetic contexts.

**Test suites** (Pest):

- `tests/Events/RunStartedTest.php` + 8 siblings — per-event DTO construction + property
  values.
- `tests/Events/Dispatcher/SimpleEventDispatcherTest.php` — subscribe + dispatch delivery
  order; `StoppableEventInterface` short-circuits; subclass matching; multiple listeners
  per event; unsubscribed event dispatches with no listener silently returns event.
- `tests/Events/Dispatcher/NullEventDispatcherTest.php` — dispatch returns event, invokes
  nothing.
- `tests/Events/Listener/LedgerAppendingListenerTest.php` — each of 9 events → correct
  `->append(executionId, string, payload)` call. Uses spy `EventLedgerInterface`.
  Includes payload-shape assertions for error events (`class` + `message` keys).
- `tests/Events/Listener/LedgerAppendingListenerTest.php::registerAll` — after
  `registerAll(dispatcher, ledger)`, dispatching each of the 9 events triggers exactly
  one ledger `append` call.
- `tests/Execution/WorkflowExecutorEventsTest.php` — 2-step happy path emits
  `RunStarted → StepStarted → StepExecuted → StepStarted → StepExecuted → RunCompleted`
  with correct `executionId`/`workflowId`/`stepId`.
- `tests/Execution/WorkflowExecutorEventsTest.php` (failing step) — emits
  `RunStarted → StepStarted → StepFailed → RunFailed`.
- `tests/Execution/StepExecutionWorkerEventsTest.php` — sync happy path emits
  `StepStarted → StepExecuted`; suspended `receive` step emits
  `StepStarted → CorrelationPending`; failing protocol executor emits
  `StepStarted → StepFailed`.
- `tests/Execution/CorrelationResumerEventsTest.php` — successful resume emits
  `CorrelationResumed` after `pendingCorrelations->consume()`.
- `tests/Execution/StepOutcomeHandlerEventsTest.php` — `RetryAction` triggers
  `StepRetried`; `SuccessEndAction` triggers `RunCompleted`; `FailureEndAction` triggers
  `RunFailed`.
- `tests/Laravel/IlluminatePsrEventDispatcherTest.php` — Orchestra Testbench boot;
  Illuminate listener registered for `RunStarted::class` fires when the adapter
  dispatches.
- `tests/Laravel/ServiceProviderBindingsTest.php` — default `EventDispatcherInterface`
  binding = `SimpleEventDispatcher`; `LedgerAppendingListener` auto-wired when
  `EventLedgerInterface` bound; `IlluminatePsrEventDispatcher` NOT bound by default.

**Regression sweep:**

- Existing ledger DB tests unchanged. Ledger receives exactly the same set of
  `->append(...)` calls as before (9 events routed through listener + 6 non-catalog
  events fired directly).
- Existing `WorkflowExecutor` tests still pass with `NullEventDispatcher` default.
- PHPStan max clean.

## Migration + CHANGELOG

CHANGELOG under `## Unreleased`:

`### Added`

- Framework-agnostic PSR-14 event bus with 9 canonical lifecycle events (`RunStarted`,
  `RunCompleted`, `RunFailed`, `StepStarted`, `StepExecuted`, `StepRetried`, `StepFailed`,
  `CorrelationPending`, `CorrelationResumed`).
- `SimpleEventDispatcher` (in-memory) and `NullEventDispatcher` (no-op) — both PSR-14.
- `LedgerAppendingListener` — bridges the bus to existing `EventLedgerInterface`
  (auto-registered when both `EventLedgerInterface` and `SimpleEventDispatcher` are
  container-bound).
- `IlluminatePsrEventDispatcher` — opt-in adapter, wires PSR-14 to
  `Illuminate\Events\Dispatcher`. Consumers bind manually.
- Requires `psr/event-dispatcher ^1.0`.

`### Changed`

- `Engine`, `WorkflowExecutor`, `StepExecutor`, `StepExecutionWorker`,
  `StepOutcomeHandler`, `CorrelationResumer` constructors gain an optional
  `Psr\EventDispatcher\EventDispatcherInterface` param (defaults to
  `NullEventDispatcher`). Existing consumers unaffected; container users get
  `SimpleEventDispatcher` automatically.
- The 9 event names that previously reached the ledger via direct `EventLedger::append`
  now flow through the bus + `LedgerAppendingListener`. Ledger output byte-identical.

`### Deprecated`

- `Alama\LaravelArazzo\Execution\Events\StepExecuted` (unused by engine flow) in favor
  of `Alama\LaravelArazzo\Events\StepExecuted`. Removed in a future major.

**Opt-in recipe** (for the Laravel bridge, once consumer wants Illuminate listeners):

```php
// AppServiceProvider::register()
$this->app->bind(
    \Psr\EventDispatcher\EventDispatcherInterface::class,
    \Alama\LaravelArazzo\Laravel\Events\IlluminatePsrEventDispatcher::class,
);
```

## Acceptance

Matches stub §Acceptance:

1. Core `Engine` / `WorkflowExecutor` fires all 9 events with `NullEventDispatcher`
   bound (no-op) OR `SimpleEventDispatcher` bound (delivered to subscribers) — no
   framework loaded.
2. Existing Laravel consumers listening for the legacy
   `Alama\LaravelArazzo\Execution\Events\StepExecuted` class continue to work; the class
   remains present.
3. Pro-observability packages can subscribe with a single
   `$dispatcher->subscribe(RunStarted::class, ...)` call.
4. PHPStan max clean.
5. Full Pest suite green; ledger regression zero-drift.

## Out of Scope

- Event schema versioning — v1 shape; break-glass migration comes later if needed.
- Async / queued event delivery — bridge concern (`bridge-28-horizon-telescope` or
  per-consumer).
- Removing `ExecutionLoggerInterface` — kept unchanged.
- Removing legacy `Alama\LaravelArazzo\Execution\Events\StepExecuted` — deprecated only
  in this stub; removed in a future major.
- Typed events for the 6 non-catalog ledger strings (`step.suspended`, `execution.*`) —
  future stub if consumers ask.

## References

- Stub: `docs/superpowers/roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md`
- Plan A extraction: `docs/superpowers/plans/2026-07-25-plan-a-core-extraction.md`
- Existing observability: `src/Execution/Contracts/EventLedgerInterface.php`,
  `src/Execution/Contracts/ExecutionLoggerInterface.php`,
  `src/Laravel/DatabaseEventLedger.php`.
- PSR-14: https://www.php-fig.org/psr/psr-14/
