# PSR-14 Event Dispatcher Wiring

Category: **core** · Phase: **0-foundation** · Tier: **OSS**
Enables: pro-observability, bridge-28 (Horizon/Telescope), tenant-09 (context bridges), health-23 (error triage)

## Problem

The engine currently emits one Laravel event (`StepExecuted`) hard-wired to
`Illuminate\Events`. Blocks: framework-agnostic listeners, Symfony EventDispatcher
consumers, pro-observability packages that hook lifecycle without touching engine code,
and any external audit/telemetry integration.

## Feature

Depend on `psr/event-dispatcher ^1`. Define a canonical event catalog under
`Alama\Arazzo\Events\`:

```php
final readonly class RunStarted     { public string $executionId; public string $workflowId; /* … */ }
final readonly class RunCompleted   { public string $executionId; public array $outputs; /* … */ }
final readonly class RunFailed      { public string $executionId; public \Throwable $cause; /* … */ }
final readonly class StepStarted    { public string $executionId; public string $stepId; /* … */ }
final readonly class StepExecuted   { public string $executionId; public string $stepId; public StepResult $result; }
final readonly class StepRetried    { public string $executionId; public string $stepId; public int $attempt; /* … */ }
final readonly class StepFailed     { public string $executionId; public string $stepId; public \Throwable $cause; }
final readonly class CorrelationPending  { public string $executionId; public string $stepId; public string $correlationId; }
final readonly class CorrelationResumed  { public string $executionId; public string $stepId; public string $correlationId; }
```

`Engine`, `StepExecutor`, `StepExecutionWorker`, `StepOutcomeHandler`, `CorrelationResumer`
all take `Psr\EventDispatcher\EventDispatcherInterface` via constructor injection. Default
binding = `NullEventDispatcher` (in-memory no-op).

Framework bridges bind PSR-14 → their native event system:
- Laravel: `IlluminatePsrEventDispatcher` (wraps `Illuminate\Events\Dispatcher`).
- Symfony: `symfony/event-dispatcher` implements PSR-14 natively.
- Drupal: `ContainerAwareEventDispatcher` adapter.

## Acceptance

- Core `Engine` fires all 9 events with no framework loaded.
- Existing `StepExecuted` Laravel event continues to reach Laravel listeners (via bridge
  adapter forwarding).
- Pro-observability can subscribe with a single `->subscribe(RunStarted::class, ...)` call.

## Out of scope

- Event schema versioning — v1; add if we ever need to break shape.
- Async event delivery (queue-backed listeners) — bridge concern.
