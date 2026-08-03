# PSR-14 Event Dispatcher Wiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a framework-agnostic PSR-14 event bus with 9 canonical lifecycle events, coexisting with the existing `EventLedgerInterface` via a `LedgerAppendingListener` bridge, injected into six execution classes.

**Architecture:** New `Alama\LaravelArazzo\Events\*` namespace holds 9 event DTOs, `SimpleEventDispatcher` (in-memory PSR-14) + `NullEventDispatcher` (no-op), and `LedgerAppendingListener` (bus → existing DB ledger). Six execution classes (`WorkflowExecutor`, `Engine`, `StepExecutionWorker`, `StepExecutor`, `StepOutcomeHandler`, `CorrelationResumer`) gain optional `Psr\EventDispatcher\EventDispatcherInterface` param. Laravel `IlluminatePsrEventDispatcher` adapter shipped opt-in. Framework boundary preserved so Plan A extraction is a clean namespace rewrite.

**Tech Stack:** PHP 8.4, Pest 4, PHPStan (larastan max), Laravel package (Illuminate contracts ^11-13), `psr/event-dispatcher ^1.0` (new dep), Orchestra Testbench 11.

## Global Constraints

- PHP version: `^8.4`.
- Test framework: Pest 4 (`vendor/bin/pest`).
- Static analysis: PHPStan max level, must stay clean (`vendor/bin/phpstan analyse`).
- Formatter: Laravel Pint (`vendor/bin/pint`).
- Pre-push gate: `pint --test` + `phpstan` + `pest --ci` (via `make verify`).
- Namespace root: `Alama\LaravelArazzo\` → `src/`. Test namespace: `Alama\LaravelArazzo\Tests\` → `tests/`.
- New Composer dep: `psr/event-dispatcher: ^1.0` (framework-agnostic; slotted for `packages/core/composer.json` post-extraction per Plan A).
- Framework boundary: event DTOs + dispatchers + `LedgerAppendingListener` are framework-agnostic (no Illuminate imports). Only `IlluminatePsrEventDispatcher` may import `Illuminate\Contracts\Events\Dispatcher`.
- Constructor signature changes to the six execution classes MUST default the new param to `null` (auto-substitute `NullEventDispatcher`) so existing test constructors keep compiling.
- Ledger output MUST remain byte-identical to pre-change — verified via a regression test comparing ledger `append` calls before + after refactor.
- Legacy `Alama\LaravelArazzo\Execution\Events\StepExecuted` kept, marked `@deprecated`. NOT removed.
- Commit convention: Conventional Commits.

---

## File Structure

**New files (source):**

- `src/Events/RunStarted.php`
- `src/Events/RunCompleted.php`
- `src/Events/RunFailed.php`
- `src/Events/StepStarted.php`
- `src/Events/StepExecuted.php`
- `src/Events/StepRetried.php`
- `src/Events/StepFailed.php`
- `src/Events/CorrelationPending.php`
- `src/Events/CorrelationResumed.php`
- `src/Events/Dispatcher/SimpleEventDispatcher.php`
- `src/Events/Dispatcher/NullEventDispatcher.php`
- `src/Events/Listener/LedgerAppendingListener.php`
- `src/Laravel/Events/IlluminatePsrEventDispatcher.php`

**Modified files (source):**

- `composer.json` — add `psr/event-dispatcher ^1.0`.
- `src/Execution/Events/StepExecuted.php` — add `@deprecated` docblock.
- `src/Execution/WorkflowExecutor.php` — inject dispatcher, dispatch RunStarted/StepStarted/StepExecuted/RunCompleted/StepFailed/RunFailed.
- `src/Execution/Engine.php` — inject dispatcher, dispatch RunStarted (with de-dup guard).
- `src/Execution/StepExecutionWorker.php` — inject dispatcher, dispatch StepStarted/StepExecuted/CorrelationPending/StepFailed. Remove now-redundant `EventLedger::append` calls for events in catalog.
- `src/Execution/StepExecutor.php` — inject dispatcher (parameter added; no dispatch sites yet — reserved for future retry-emitter hook).
- `src/Execution/StepOutcomeHandler.php` — inject dispatcher, dispatch StepRetried/RunCompleted/RunFailed on terminal actions.
- `src/Execution/CorrelationResumer.php` — inject dispatcher, dispatch CorrelationResumed. Remove now-redundant `EventLedger::append('step.resumed', ...)` — wait, `step.resumed` is a NON-catalog event; keep the direct append. Verify during implementation.
- `src/LaravelArazzoServiceProvider.php` — bind `EventDispatcherInterface` → `SimpleEventDispatcher`; auto-wire `LedgerAppendingListener` when `EventLedgerInterface` is bound.

**New test files:**

- One per event DTO (9 files): `tests/Events/*Test.php`.
- `tests/Events/Dispatcher/SimpleEventDispatcherTest.php`
- `tests/Events/Dispatcher/NullEventDispatcherTest.php`
- `tests/Events/Listener/LedgerAppendingListenerTest.php`
- `tests/Execution/WorkflowExecutorEventsTest.php`
- `tests/Execution/EngineEventsTest.php`
- `tests/Execution/StepExecutionWorkerEventsTest.php`
- `tests/Execution/StepOutcomeHandlerEventsTest.php`
- `tests/Execution/CorrelationResumerEventsTest.php`
- `tests/Execution/LedgerRegressionTest.php` — asserts ledger output byte-identical vs. pre-refactor baseline.
- `tests/Laravel/IlluminatePsrEventDispatcherTest.php`
- `tests/Laravel/EventDispatcherBindingTest.php`

---

### Task 1: Composer dep + 9 Event DTOs

**Files:**
- Modify: `composer.json`
- Create: `src/Events/RunStarted.php`
- Create: `src/Events/RunCompleted.php`
- Create: `src/Events/RunFailed.php`
- Create: `src/Events/StepStarted.php`
- Create: `src/Events/StepExecuted.php`
- Create: `src/Events/StepRetried.php`
- Create: `src/Events/StepFailed.php`
- Create: `src/Events/CorrelationPending.php`
- Create: `src/Events/CorrelationResumed.php`
- Test: `tests/Events/EventShapesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: 9 `final readonly class` DTOs under `Alama\LaravelArazzo\Events\`. Every class carries `public string $executionId`, `public \DateTimeImmutable $at`. Most carry `public string $workflowId`. See spec §Event Catalog for exact per-class field lists.

- [ ] **Step 1: Add Composer dependency**

Modify `composer.json`. Under `"require"`, add:

```json
"psr/event-dispatcher": "^1.0",
```

Keep alphabetical order among neighbors. Run: `composer validate --strict` — expected valid.

Run: `composer update psr/event-dispatcher --no-scripts` to pull the package into `vendor/`.

- [ ] **Step 2: Write failing test**

Create `tests/Events/EventShapesTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Alama\LaravelArazzo\Events\StepStarted;

it('constructs RunStarted with all fields', function () {
    $at = new DateTimeImmutable();
    $e = new RunStarted('exec-1', 'wf', 'def', ['x' => 1], $at);
    expect($e->executionId)->toBe('exec-1')
        ->and($e->workflowId)->toBe('wf')
        ->and($e->definitionId)->toBe('def')
        ->and($e->inputs)->toBe(['x' => 1])
        ->and($e->at)->toBe($at);
});

it('constructs RunCompleted', function () {
    $e = new RunCompleted('exec-1', 'wf', ['out' => 42], new DateTimeImmutable());
    expect($e->outputs)->toBe(['out' => 42]);
});

it('constructs RunFailed with a Throwable cause', function () {
    $cause = new RuntimeException('boom');
    $e = new RunFailed('exec-1', 'wf', $cause, new DateTimeImmutable());
    expect($e->cause)->toBe($cause);
});

it('constructs StepStarted', function () {
    $e = new StepStarted('exec-1', 'wf', 'stepA', 2, new DateTimeImmutable());
    expect($e->stepId)->toBe('stepA')->and($e->attempt)->toBe(2);
});

it('constructs StepExecuted', function () {
    $e = new StepExecuted('exec-1', 'wf', 'stepA', 200, ['id' => 42], true, new DateTimeImmutable());
    expect($e->statusCode)->toBe(200)
        ->and($e->outputs)->toBe(['id' => 42])
        ->and($e->criteriaMet)->toBeTrue();
});

it('constructs StepRetried with nullable lastError', function () {
    $e1 = new StepRetried('exec-1', 'wf', 'stepA', 3, null, new DateTimeImmutable());
    expect($e1->lastError)->toBeNull();

    $err = new RuntimeException('nope');
    $e2 = new StepRetried('exec-1', 'wf', 'stepA', 3, $err, new DateTimeImmutable());
    expect($e2->lastError)->toBe($err);
});

it('constructs StepFailed', function () {
    $e = new StepFailed('exec-1', 'wf', 'stepA', new RuntimeException('x'), new DateTimeImmutable());
    expect($e->cause)->toBeInstanceOf(RuntimeException::class);
});

it('constructs CorrelationPending', function () {
    $e = new CorrelationPending('exec-1', 'wf', 'stepA', 'corr-9', 'channels/x', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9')->and($e->channelPath)->toBe('channels/x');
});

it('constructs CorrelationResumed', function () {
    $e = new CorrelationResumed('exec-1', 'wf', 'stepA', 'corr-9', new DateTimeImmutable());
    expect($e->correlationId)->toBe('corr-9');
});
```

- [ ] **Step 3: Run to see it fail**

Run: `vendor/bin/pest tests/Events/EventShapesTest.php`
Expected: FAIL — event classes not found.

- [ ] **Step 4: Create 9 event DTOs**

Create `src/Events/RunStarted.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class RunStarted
{
    /**
     * @param array<string, mixed> $inputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $definitionId,
        public array $inputs,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/RunCompleted.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class RunCompleted
{
    /**
     * @param array<string, mixed> $outputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public array $outputs,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/RunFailed.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class RunFailed
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public \Throwable $cause,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/StepStarted.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepStarted
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/StepExecuted.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepExecuted
{
    /**
     * @param array<string, mixed> $outputs
     */
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $statusCode,
        public array $outputs,
        public bool $criteriaMet,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/StepRetried.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepRetried
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public int $attempt,
        public ?\Throwable $lastError,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/StepFailed.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class StepFailed
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public \Throwable $cause,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/CorrelationPending.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class CorrelationPending
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public string $channelPath,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

Create `src/Events/CorrelationResumed.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events;

final readonly class CorrelationResumed
{
    public function __construct(
        public string $executionId,
        public string $workflowId,
        public string $stepId,
        public string $correlationId,
        public \DateTimeImmutable $at,
    ) {
    }
}
```

- [ ] **Step 5: Mark legacy `StepExecuted` deprecated**

Modify `src/Execution/Events/StepExecuted.php`. Above `class StepExecuted`, add:

```php
/**
 * @deprecated Since core-38. Use \Alama\LaravelArazzo\Events\StepExecuted (PSR-14 event). Will be removed in a future major.
 */
class StepExecuted
```

- [ ] **Step 6: Run tests + PHPStan**

Run: `vendor/bin/pest tests/Events/EventShapesTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock src/Events/ src/Execution/Events/StepExecuted.php tests/Events/EventShapesTest.php
git commit -m "feat(events): add 9 PSR-14 lifecycle event DTOs (core-38)"
```

---

### Task 2: `NullEventDispatcher` + `SimpleEventDispatcher`

**Files:**
- Create: `src/Events/Dispatcher/NullEventDispatcher.php`
- Create: `src/Events/Dispatcher/SimpleEventDispatcher.php`
- Test: `tests/Events/Dispatcher/NullEventDispatcherTest.php`
- Test: `tests/Events/Dispatcher/SimpleEventDispatcherTest.php`

**Interfaces:**
- Consumes: `Psr\EventDispatcher\EventDispatcherInterface`, `Psr\EventDispatcher\ListenerProviderInterface`, `Psr\EventDispatcher\StoppableEventInterface`.
- Produces:
  - `NullEventDispatcher::dispatch(object $event): object` — returns event unchanged.
  - `SimpleEventDispatcher::subscribe(string $eventClass, callable $listener): void`.
  - `SimpleEventDispatcher::dispatch(object $event): object` — invokes each matching listener; respects `StoppableEventInterface`.
  - `SimpleEventDispatcher::getListenersForEvent(object $event): iterable<callable>` — subclass-matching.

- [ ] **Step 1: Write failing tests**

Create `tests/Events/Dispatcher/NullEventDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;

it('returns the event unchanged', function () {
    $event = new stdClass();
    expect((new NullEventDispatcher())->dispatch($event))->toBe($event);
});
```

Create `tests/Events/Dispatcher/SimpleEventDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunStarted;
use Psr\EventDispatcher\StoppableEventInterface;

it('delivers to subscribed listeners in subscription order', function () {
    $log = [];
    $d = new SimpleEventDispatcher();
    $d->subscribe(RunStarted::class, function ($e) use (&$log) { $log[] = 'a:' . $e->executionId; });
    $d->subscribe(RunStarted::class, function ($e) use (&$log) { $log[] = 'b:' . $e->executionId; });

    $event = new RunStarted('exec-1', 'w', 'd', [], new DateTimeImmutable());
    $d->dispatch($event);

    expect($log)->toBe(['a:exec-1', 'b:exec-1']);
});

it('returns the event object from dispatch', function () {
    $d = new SimpleEventDispatcher();
    $event = new RunStarted('exec-1', 'w', 'd', [], new DateTimeImmutable());
    expect($d->dispatch($event))->toBe($event);
});

it('is a no-op for events with no subscribers', function () {
    $d = new SimpleEventDispatcher();
    $event = new RunStarted('exec-1', 'w', 'd', [], new DateTimeImmutable());
    expect($d->dispatch($event))->toBe($event); // does not throw
});

it('matches listeners registered for parent class or interface', function () {
    $captured = null;
    $d = new SimpleEventDispatcher();
    $d->subscribe(stdClass::class, function ($e) use (&$captured) { $captured = $e; });

    $event = new class extends stdClass {};
    $d->dispatch($event);

    expect($captured)->toBe($event);
});

it('respects StoppableEventInterface propagation', function () {
    $log = [];
    $d = new SimpleEventDispatcher();

    $stoppable = new class implements StoppableEventInterface {
        public bool $stopped = false;
        public function isPropagationStopped(): bool { return $this->stopped; }
    };

    $d->subscribe($stoppable::class, function ($e) use (&$log) { $log[] = 'first'; $e->stopped = true; });
    $d->subscribe($stoppable::class, function ($e) use (&$log) { $log[] = 'second'; });

    $d->dispatch($stoppable);
    expect($log)->toBe(['first']);
});
```

- [ ] **Step 2: Run to see them fail**

Run: `vendor/bin/pest tests/Events/Dispatcher/`
Expected: FAIL — classes not found.

- [ ] **Step 3: Create `NullEventDispatcher`**

Create `src/Events/Dispatcher/NullEventDispatcher.php`:

```php
<?php

declare(strict_types=1);

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

- [ ] **Step 4: Create `SimpleEventDispatcher`**

Create `src/Events/Dispatcher/SimpleEventDispatcher.php`:

```php
<?php

declare(strict_types=1);

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

- [ ] **Step 5: Run tests + PHPStan**

Run: `vendor/bin/pest tests/Events/Dispatcher/`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Events/Dispatcher/ tests/Events/Dispatcher/
git commit -m "feat(events): SimpleEventDispatcher + NullEventDispatcher (PSR-14)"
```

---

### Task 3: `LedgerAppendingListener` — bus → ledger bridge

**Files:**
- Create: `src/Events/Listener/LedgerAppendingListener.php`
- Test: `tests/Events/Listener/LedgerAppendingListenerTest.php`

**Interfaces:**
- Consumes: 9 event DTOs (Task 1), `SimpleEventDispatcher::subscribe` (Task 2), `EventLedgerInterface::append(string $executionId, string $eventType, array $payload)`.
- Produces:
  - `LedgerAppendingListener::__construct(EventLedgerInterface $ledger)`
  - `LedgerAppendingListener::__invoke(object $event): void`
  - `LedgerAppendingListener::registerAll(SimpleEventDispatcher $dispatcher, EventLedgerInterface $ledger): void`

Ledger mapping (spec §Ledger Bridge):

| Event | String | Payload |
|---|---|---|
| RunStarted | run.started | workflowId, definitionId, inputs |
| RunCompleted | run.completed | workflowId, outputs |
| RunFailed | run.failed | workflowId, error{class,message} |
| StepStarted | step.started | stepId, attempt |
| StepExecuted | step.executed | stepId, statusCode, outputs, criteriaMet |
| StepRetried | step.retried | stepId, attempt, lastError{class,message}\|null |
| StepFailed | step.failed | stepId, error{class,message} |
| CorrelationPending | correlation.pending | stepId, correlationId, channelPath |
| CorrelationResumed | correlation.resumed | stepId, correlationId |

- [ ] **Step 1: Write failing test**

Create `tests/Events/Listener/LedgerAppendingListenerTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Alama\LaravelArazzo\Events\StepStarted;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;

class SpyLedger implements EventLedgerInterface {
    /** @var list<array{executionId: string, type: string, payload: array<string, mixed>}> */
    public array $appended = [];
    public function append(string $executionId, string $eventType, array $payload): void {
        $this->appended[] = ['executionId' => $executionId, 'type' => $eventType, 'payload' => $payload];
    }
}

function ledgerListener(): array {
    $spy = new SpyLedger();
    return [$spy, new LedgerAppendingListener($spy)];
}

it('maps RunStarted to run.started', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunStarted('exec-1', 'w', 'def', ['k' => 1], new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'run.started',
        'payload' => ['workflowId' => 'w', 'definitionId' => 'def', 'inputs' => ['k' => 1]],
    ]]);
});

it('maps RunCompleted to run.completed', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunCompleted('exec-1', 'w', ['out' => 42], new DateTimeImmutable()));
    expect($spy->appended[0]['type'])->toBe('run.completed')
        ->and($spy->appended[0]['payload'])->toBe(['workflowId' => 'w', 'outputs' => ['out' => 42]]);
});

it('maps RunFailed to run.failed with error shape', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunFailed('exec-1', 'w', new RuntimeException('boom'), new DateTimeImmutable()));
    expect($spy->appended[0]['type'])->toBe('run.failed')
        ->and($spy->appended[0]['payload'])->toBe(['workflowId' => 'w', 'error' => ['class' => RuntimeException::class, 'message' => 'boom']]);
});

it('maps StepStarted, StepExecuted, StepRetried, StepFailed', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepStarted('e', 'w', 's', 2, new DateTimeImmutable()));
    $l(new StepExecuted('e', 'w', 's', 200, ['id' => 1], true, new DateTimeImmutable()));
    $l(new StepRetried('e', 'w', 's', 3, new RuntimeException('x'), new DateTimeImmutable()));
    $l(new StepFailed('e', 'w', 's', new RuntimeException('y'), new DateTimeImmutable()));

    $types = array_column($spy->appended, 'type');
    expect($types)->toBe(['step.started', 'step.executed', 'step.retried', 'step.failed']);

    expect($spy->appended[0]['payload'])->toBe(['stepId' => 's', 'attempt' => 2])
        ->and($spy->appended[1]['payload'])->toBe(['stepId' => 's', 'statusCode' => 200, 'outputs' => ['id' => 1], 'criteriaMet' => true])
        ->and($spy->appended[2]['payload']['lastError'])->toBe(['class' => RuntimeException::class, 'message' => 'x'])
        ->and($spy->appended[3]['payload']['error'])->toBe(['class' => RuntimeException::class, 'message' => 'y']);
});

it('handles StepRetried with null lastError', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepRetried('e', 'w', 's', 1, null, new DateTimeImmutable()));
    expect($spy->appended[0]['payload']['lastError'])->toBeNull();
});

it('maps correlation events', function () {
    [$spy, $l] = ledgerListener();
    $l(new CorrelationPending('e', 'w', 's', 'corr-1', 'ch/x', new DateTimeImmutable()));
    $l(new CorrelationResumed('e', 'w', 's', 'corr-1', new DateTimeImmutable()));
    expect($spy->appended[0]['type'])->toBe('correlation.pending')
        ->and($spy->appended[0]['payload'])->toBe(['stepId' => 's', 'correlationId' => 'corr-1', 'channelPath' => 'ch/x'])
        ->and($spy->appended[1]['type'])->toBe('correlation.resumed')
        ->and($spy->appended[1]['payload'])->toBe(['stepId' => 's', 'correlationId' => 'corr-1']);
});

it('registers all 9 events via registerAll and each dispatch appends once', function () {
    $spy = new SpyLedger();
    $d = new SimpleEventDispatcher();
    LedgerAppendingListener::registerAll($d, $spy);

    foreach ([
        new RunStarted('e', 'w', 'd', [], new DateTimeImmutable()),
        new RunCompleted('e', 'w', [], new DateTimeImmutable()),
        new RunFailed('e', 'w', new RuntimeException('x'), new DateTimeImmutable()),
        new StepStarted('e', 'w', 's', 1, new DateTimeImmutable()),
        new StepExecuted('e', 'w', 's', 200, [], true, new DateTimeImmutable()),
        new StepRetried('e', 'w', 's', 2, null, new DateTimeImmutable()),
        new StepFailed('e', 'w', 's', new RuntimeException('y'), new DateTimeImmutable()),
        new CorrelationPending('e', 'w', 's', 'c', 'ch', new DateTimeImmutable()),
        new CorrelationResumed('e', 'w', 's', 'c', new DateTimeImmutable()),
    ] as $event) {
        $d->dispatch($event);
    }

    expect($spy->appended)->toHaveCount(9);
});
```

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Events/Listener/LedgerAppendingListenerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create listener**

Create `src/Events/Listener/LedgerAppendingListener.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events\Listener;

use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Alama\LaravelArazzo\Events\StepStarted;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;

final class LedgerAppendingListener
{
    public function __construct(private EventLedgerInterface $ledger) {}

    public function __invoke(object $event): void
    {
        [$type, $payload] = match (true) {
            $event instanceof RunStarted => [
                'run.started',
                ['workflowId' => $event->workflowId, 'definitionId' => $event->definitionId, 'inputs' => $event->inputs],
            ],
            $event instanceof RunCompleted => [
                'run.completed',
                ['workflowId' => $event->workflowId, 'outputs' => $event->outputs],
            ],
            $event instanceof RunFailed => [
                'run.failed',
                ['workflowId' => $event->workflowId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof StepStarted => [
                'step.started',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt],
            ],
            $event instanceof StepExecuted => [
                'step.executed',
                ['stepId' => $event->stepId, 'statusCode' => $event->statusCode, 'outputs' => $event->outputs, 'criteriaMet' => $event->criteriaMet],
            ],
            $event instanceof StepRetried => [
                'step.retried',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt, 'lastError' => $event->lastError !== null ? self::errorPayload($event->lastError) : null],
            ],
            $event instanceof StepFailed => [
                'step.failed',
                ['stepId' => $event->stepId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof CorrelationPending => [
                'correlation.pending',
                ['stepId' => $event->stepId, 'correlationId' => $event->correlationId, 'channelPath' => $event->channelPath],
            ],
            $event instanceof CorrelationResumed => [
                'correlation.resumed',
                ['stepId' => $event->stepId, 'correlationId' => $event->correlationId],
            ],
            default => [null, null],
        };

        if ($type === null) {
            return;
        }

        /** @var object{executionId: string} $event */
        $this->ledger->append($event->executionId, $type, $payload);
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

    /** @return array{class: class-string<\Throwable>, message: string} */
    private static function errorPayload(\Throwable $t): array
    {
        return ['class' => $t::class, 'message' => $t->getMessage()];
    }
}
```

- [ ] **Step 4: Run tests + PHPStan**

Run: `vendor/bin/pest tests/Events/Listener/LedgerAppendingListenerTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Events/Listener/ tests/Events/Listener/
git commit -m "feat(events): LedgerAppendingListener bridges bus -> EventLedger"
```

---

### Task 4: `WorkflowExecutor` injection + dispatch

**Files:**
- Modify: `src/Execution/WorkflowExecutor.php`
- Test: `tests/Execution/WorkflowExecutorEventsTest.php`

**Interfaces:**
- Consumes: `Psr\EventDispatcher\EventDispatcherInterface`, `NullEventDispatcher` (Task 2), all 4 event classes it emits: `RunStarted`, `StepStarted`, `StepExecuted`, `StepFailed`, `RunCompleted`, `RunFailed`.
- Produces: `WorkflowExecutor` now dispatches `RunStarted` before step loop; `StepStarted` before each step; `StepExecuted` on success; `StepFailed` on failure; `RunCompleted` after loop; `RunFailed` on caught exception. Constructor gains `?EventDispatcherInterface $events = null`.

- [ ] **Step 1: Write failing test**

Create `tests/Execution/WorkflowExecutorEventsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted as EventStepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepStarted;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Execution\WorkflowExecutor;

// Anonymous StepExecutor subclass that records dispatches
class RecordingStepExec extends StepExecutor {
    public function __construct(public bool $succeed = true) {}
    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array {
        return [$context->withStepResult($step->stepId, ['outputs' => ['x' => 1]]), $this->succeed];
    }
}

function docWithWorkflow(Workflow $wf): ArazzoDocument {
    return new ArazzoDocument(
        arazzo: '1.0.0', info: new Info('t', null, null, '1'),
        sourceDescriptions: [], workflows: [$wf],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function captureEvents(SimpleEventDispatcher $d, array &$log): void {
    foreach ([RunStarted::class, RunCompleted::class, RunFailed::class,
              StepStarted::class, EventStepExecuted::class, StepFailed::class] as $cls) {
        $d->subscribe($cls, function ($e) use (&$log, $cls) { $log[] = basename(str_replace('\\', '/', $cls)); });
    }
}

it('dispatches happy-path sequence RunStarted -> StepStarted -> StepExecuted -> RunCompleted', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(new RecordingStepExec(), null, $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStarted', 'StepStarted', 'StepExecuted', 'RunCompleted']);
});

it('dispatches StepFailed + RunFailed on step failure', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $d = new SimpleEventDispatcher();
    $log = [];
    captureEvents($d, $log);

    (new WorkflowExecutor(new RecordingStepExec(succeed: false), null, $d))->execute($wf, docWithWorkflow($wf), []);

    expect($log)->toBe(['RunStarted', 'StepStarted', 'StepFailed', 'RunFailed']);
});
```

If `WorkflowExecutor` constructor's third param slot differs (per prior tasks it may have `?ExecutionLoggerInterface` as position 2), adapt this test accordingly.

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorEventsTest.php`
Expected: FAIL — constructor rejects third arg (or events not dispatched).

- [ ] **Step 3: Modify `WorkflowExecutor`**

Modify `src/Execution/WorkflowExecutor.php`. Add imports:

```php
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\LaravelArazzo\Events\StepFailed as StepFailedEvent;
use Alama\LaravelArazzo\Events\StepStarted;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
```

Extend constructor + execute:

```php
    private EventDispatcherInterface $events;

    public function __construct(
        private StepExecutor $stepExecutor,
        private ?ExecutionLoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function execute(Workflow $workflow, ArazzoDocument $document, array $inputs): ExecutionResult
    {
        $executionId = $inputs['__executionId'] ?? bin2hex(random_bytes(8));
        $context = new WorkflowContext($workflow->workflowId, $inputs);

        $this->events->dispatch(new RunStarted(
            $executionId, $workflow->workflowId, $workflow->workflowId, $inputs, new \DateTimeImmutable(),
        ));

        $stepResults = [];
        try {
            foreach ($workflow->steps as $step) {
                $stepId = $step->stepId;
                $this->logger?->logStepStarted($stepId);
                $this->events->dispatch(new StepStarted(
                    $executionId, $workflow->workflowId, $stepId, 1, new \DateTimeImmutable(),
                ));

                [$context, $success] = $this->stepExecutor->execute($step, $context, $document);
                $outputs = $context->getSteps()[$stepId]['outputs'] ?? [];
                $result = new StepResult($stepId, $success, $outputs);
                $stepResults[$stepId] = $result;

                if (!$success) {
                    $cause = new \RuntimeException("Step '{$stepId}' failed");
                    $this->logger?->logStepFailed($stepId, $cause);
                    $this->events->dispatch(new StepFailedEvent(
                        $executionId, $workflow->workflowId, $stepId, $cause, new \DateTimeImmutable(),
                    ));
                    $this->events->dispatch(new RunFailed(
                        $executionId, $workflow->workflowId, $cause, new \DateTimeImmutable(),
                    ));
                    return new ExecutionResult($workflow->workflowId, 'failed', [], $stepResults);
                }

                $this->events->dispatch(new StepExecutedEvent(
                    $executionId, $workflow->workflowId, $stepId,
                    (int) ($context->getSteps()[$stepId]['statusCode'] ?? 0),
                    $outputs, true, new \DateTimeImmutable(),
                ));
                $this->logger?->logStepCompleted($workflow->workflowId, $stepId, $result->outputs);
            }
        } catch (Throwable $t) {
            $this->events->dispatch(new RunFailed(
                $executionId, $workflow->workflowId, $t, new \DateTimeImmutable(),
            ));
            throw $t;
        }

        $aggregatedOutputs = [];
        foreach ($stepResults as $sid => $r) {
            $aggregatedOutputs[$sid] = $r->outputs;
        }
        $this->events->dispatch(new RunCompleted(
            $executionId, $workflow->workflowId, $aggregatedOutputs, new \DateTimeImmutable(),
        ));

        return new ExecutionResult($workflow->workflowId, 'completed', [], $stepResults);
    }
```

Note: existing `ExecutionResult` construction preserved; if any downstream test checks the `'completed'` string, unchanged.

- [ ] **Step 4: Run tests + PHPStan + full suite**

Run: `vendor/bin/pest tests/Execution/WorkflowExecutorEventsTest.php`
Expected: PASS.
Run: `vendor/bin/pest`
Expected: all existing tests still green (new events default to NullEventDispatcher).
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/WorkflowExecutor.php tests/Execution/WorkflowExecutorEventsTest.php
git commit -m "feat(executor): WorkflowExecutor dispatches PSR-14 lifecycle events"
```

---

### Task 5: `Engine` + `StepExecutionWorker` — async dispatch

**Files:**
- Modify: `src/Execution/Engine.php`
- Modify: `src/Execution/StepExecutionWorker.php`
- Test: `tests/Execution/EngineEventsTest.php`
- Test: `tests/Execution/StepExecutionWorkerEventsTest.php`

**Interfaces:**
- Consumes: `EventDispatcherInterface`, `NullEventDispatcher`, `RunStarted`, `StepStarted`, `StepExecuted`, `CorrelationPending`, `StepFailed`.
- Produces:
  - `Engine::__construct(..., ?EventDispatcherInterface $events = null)` — dispatches `RunStarted` on first `evaluate()` per `executionId` (de-dup guard).
  - `StepExecutionWorker::__construct(..., ?EventDispatcherInterface $events = null)` — dispatches `StepStarted`, `StepExecuted`, `CorrelationPending`, `StepFailed`. Removes the now-redundant `EventLedger::append('step.executed', ...)` call (routed via listener instead).

- [ ] **Step 1: Write failing tests**

Create `tests/Execution/StepExecutionWorkerEventsTest.php` (skeleton — adapt to real constructor + spy interfaces):

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\StepExecuted as EventStepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepStarted;

// This test is an integration-style test. Build the minimum harness:
// stub interfaces (LockManager, StateStore, DefinitionRegistry, EventLedger,
// ExecutionRegistry, ExpressionResolver, one StepProtocolExecutor).
// Assert dispatched event types.

it('dispatches StepStarted then StepExecuted on happy path', function () {
    // TODO(implementer): concrete wiring — see spec §Injection + Dispatch Sites.
    // Build a StepExecutionWorker with the six stubs + SimpleEventDispatcher.
    // Feed an ExecuteStepJob with a synthetic Step + ArazzoDocument.
    // Assert dispatched event sequence via a listener that captures $event::class.
    expect(true)->toBeTrue(); // placeholder — flesh out in Step 3
})->skip('Skeleton — implementer expands after reading StepExecutionWorker constructor.');
```

The skip tag here is a real-world honesty check: the worker has 9 constructor deps; test authorship requires reading the real signatures first. Implementer removes the skip when wired.

Create `tests/Execution/EngineEventsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\WorkflowContext;

class NoopQueue implements \Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface {
    public array $dispatched = [];
    public function dispatch(object $job): void { $this->dispatched[] = $job; }
}
class NoopStateStore implements \Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface {
    public function load(string $id): ?array { return null; }
    public function save(string $id, array $state, int $ttl): void {}
}

it('dispatches RunStarted on first evaluate per executionId', function () {
    $step = new Step('A', null, 'op', null, null, [], null, [], [], [], []);
    $wf = new Workflow('w', null, null, null, [], [$step], [], [], [], []);

    $ctx = new WorkflowContext('def', [], [], [], 'w', 'exec-1');
    $d = new SimpleEventDispatcher();

    $captured = [];
    $d->subscribe(RunStarted::class, function ($e) use (&$captured) { $captured[] = $e->executionId; });

    // Adapt Engine constructor signature — inspect current form.
    $engine = new Engine(new DependencyAnalyzer(), new NoopQueue(), new NoopStateStore(), $d);
    $engine->evaluate($wf, $ctx);
    $engine->evaluate($wf, $ctx); // second call: should NOT re-fire RunStarted

    expect($captured)->toBe(['exec-1']);
});
```

Note: adapt `Engine` constructor to whatever the current codebase has (may already have been refactored by core-37). This plan writes against the pre-core-37 signature; adjust if core-37 landed first.

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Execution/EngineEventsTest.php tests/Execution/StepExecutionWorkerEventsTest.php`
Expected: FAIL — `Engine` constructor doesn't accept 4th arg; worker test is skipped.

- [ ] **Step 3: Modify `Engine`**

Modify `src/Execution/Engine.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Psr\EventDispatcher\EventDispatcherInterface;

class Engine
{
    private EventDispatcherInterface $events;

    /** @var array<string, true> executionIds for which RunStarted has fired */
    private array $started = [];

    public function __construct(
        private DependencyAnalyzer $analyzer,
        private QueueDriverInterface $queueDriver,
        /** @phpstan-ignore property.onlyWritten */
        private StateStoreInterface $stateStore,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function evaluate(Workflow $workflow, WorkflowContext $context): void
    {
        if ($context->getWorkflowId() === null) {
            $context = $context->withWorkflowId($workflow->workflowId);
        }

        $executionId = $context->getExecutionId();
        if ($executionId !== null && !isset($this->started[$executionId])) {
            $this->started[$executionId] = true;
            $this->events->dispatch(new RunStarted(
                $executionId,
                $workflow->workflowId,
                $context->getDefinitionId(),
                $context->getInputs(),
                new \DateTimeImmutable(),
            ));
        }

        $runnableSteps = $this->analyzer->getRunnableSteps($workflow->steps, $context);

        if (empty($runnableSteps)) {
            return;
        }

        foreach ($runnableSteps as $step) {
            $this->queueDriver->dispatch(new ExecuteStepJob($step, $context));
        }
    }
}
```

If core-37 already refactored `Engine` (took `DependencyGraph` per workflow), preserve that structure and add the events param + RunStarted dispatch on top.

- [ ] **Step 4: Modify `StepExecutionWorker`**

Modify `src/Execution/StepExecutionWorker.php`. Add imports:

```php
use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\LaravelArazzo\Events\StepExecuted as StepExecutedEvent;
use Alama\LaravelArazzo\Events\StepFailed as StepFailedEvent;
use Alama\LaravelArazzo\Events\StepStarted;
use Psr\EventDispatcher\EventDispatcherInterface;
```

Extend constructor:

```php
    private EventDispatcherInterface $events;

    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private DefinitionRegistryInterface $definitionRegistry,
        private EventLedgerInterface $eventLedger,
        private ExecutionRegistryInterface $executionRegistry,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
        private StepOutcomeHandler $outcomeHandler,
        private ?LoggerInterface $logger = null,
        private int $stateTtlSeconds = 86400,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }
```

Add dispatch calls inside the lock closure in `handle()`:

- Right after `$executionId` is captured:

```php
$attempt = $context->getStepAttempts($step->stepId) + 1;
$this->events->dispatch(new StepStarted(
    $executionId, $context->getWorkflowId() ?? '', $step->stepId, $attempt, new \DateTimeImmutable(),
));
```

- Inside `if ($outcome->suspended)` branch, after the `$this->eventLedger->append(..., 'step.suspended', ...)`:

```php
if ($step->action === 'receive' && $step->correlationId !== null && $step->channelPath !== null) {
    $correlationIdValue = (string) $this->expressionResolver->evaluate($step->correlationId, $context, $step->stepId);
    $this->events->dispatch(new CorrelationPending(
        $executionId, $context->getWorkflowId() ?? '', $step->stepId,
        $correlationIdValue, $step->channelPath, new \DateTimeImmutable(),
    ));
}
```

- After the successful `$this->outcomeHandler->handle(...)` call at bottom of the lock closure, replace the existing `eventLedger->append(..., 'step.executed', ...)` block with:

```php
$this->events->dispatch(new StepExecutedEvent(
    $executionId, $workflow->workflowId, $step->stepId,
    $outcome->statusCode, $outcome->outputs, $criteriaMet, new \DateTimeImmutable(),
));
```

Delete the try/catch block around the removed `eventLedger->append(..., 'step.executed', ...)` — the same info now flows through the bus → LedgerAppendingListener. Non-catalog `step.suspended`, `execution.*` appends remain untouched.

- Wrap the whole lock body's outer try/catch to dispatch `StepFailed` on unexpected exceptions:

```php
try {
    // existing lock closure body
} catch (Throwable $t) {
    $this->events->dispatch(new StepFailedEvent(
        $executionId, $context->getWorkflowId() ?? '', $step->stepId, $t, new \DateTimeImmutable(),
    ));
    throw $t;
}
```

- [ ] **Step 5: Flesh out the skipped worker test**

Return to `tests/Execution/StepExecutionWorkerEventsTest.php`. Remove `->skip(...)`. Write concrete tests using stub implementations of the 9 dep interfaces + a `SimpleEventDispatcher`. Assert:
- Happy path dispatches `[StepStarted, StepExecuted]`.
- `action: receive` suspend dispatches `[StepStarted, CorrelationPending]`.
- Throwing protocol executor dispatches `[StepStarted, StepFailed]` and re-raises.

- [ ] **Step 6: Run tests + full suite + PHPStan**

Run: `vendor/bin/pest tests/Execution/`
Expected: all PASS.
Run: `vendor/bin/pest && vendor/bin/phpstan analyse --no-progress`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add src/Execution/Engine.php src/Execution/StepExecutionWorker.php tests/Execution/EngineEventsTest.php tests/Execution/StepExecutionWorkerEventsTest.php
git commit -m "feat(engine): Engine + StepExecutionWorker dispatch async lifecycle events"
```

---

### Task 6: `StepOutcomeHandler` — terminal + retry dispatch

**Files:**
- Modify: `src/Execution/StepOutcomeHandler.php`
- Test: `tests/Execution/StepOutcomeHandlerEventsTest.php`

**Interfaces:**
- Consumes: `EventDispatcherInterface`, `NullEventDispatcher`, `StepRetried`, `RunCompleted`, `RunFailed`.
- Produces: `StepOutcomeHandler::__construct(..., ?EventDispatcherInterface $events = null)`. Dispatches:
  - `StepRetried` when a `RetryAction` fires (retry job re-queued).
  - `RunCompleted` when a `SuccessEndAction` terminates.
  - `RunFailed` when a `FailureEndAction` terminates.

- [ ] **Step 1: Inspect handler internals**

Read `src/Execution/StepOutcomeHandler.php` in full — it's 312 lines. Identify:
- Where `RetryAction` branches → dispatch `StepRetried` right before the retry job is queued.
- Where `SuccessEndAction` branches → dispatch `RunCompleted` with terminal outputs snapshot.
- Where `FailureEndAction` branches → dispatch `RunFailed` with a synthesized `\RuntimeException("Workflow ended in failure at step '{$stepId}'")` (no native cause available at this layer).

- [ ] **Step 2: Write failing test**

Create `tests/Execution/StepOutcomeHandlerEventsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\StepRetried;

// Integration test — construct StepOutcomeHandler with stubs, invoke handle() with
// synthetic Step + action lists exercising each branch.

it('dispatches StepRetried when RetryAction fires', function () {
    expect(true)->toBeTrue();
})->skip('Implementer wires stubs matching StepOutcomeHandler constructor.');

it('dispatches RunCompleted on SuccessEndAction terminal', function () {
    expect(true)->toBeTrue();
})->skip('Implementer wires stubs.');

it('dispatches RunFailed on FailureEndAction terminal', function () {
    expect(true)->toBeTrue();
})->skip('Implementer wires stubs.');
```

Convert skips to concrete tests after reading the handler.

- [ ] **Step 3: Run to see failures (or skips)**

Run: `vendor/bin/pest tests/Execution/StepOutcomeHandlerEventsTest.php`
Expected: 3 skipped tests. Convert to real tests before proceeding.

- [ ] **Step 4: Modify `StepOutcomeHandler`**

Add imports:

```php
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Psr\EventDispatcher\EventDispatcherInterface;
```

Add field + constructor param:

```php
    private EventDispatcherInterface $events;

    public function __construct(
        // ... existing params ...
        ?EventDispatcherInterface $events = null,
    ) {
        // ... existing assignments ...
        $this->events = $events ?? new NullEventDispatcher();
    }
```

Locate the three dispatch sites:

**RetryAction branch** — before requeueing the job:

```php
$attempt = $context->getStepAttempts($step->stepId);
$this->events->dispatch(new StepRetried(
    $executionId, $workflow->workflowId, $step->stepId,
    $attempt, null /* lastError unknown at this call site */, new \DateTimeImmutable(),
));
```

**SuccessEndAction branch** — right before returning / stopping:

```php
$this->events->dispatch(new RunCompleted(
    $executionId, $workflow->workflowId,
    $context->getSteps()[$step->stepId]['outputs'] ?? [],
    new \DateTimeImmutable(),
));
```

**FailureEndAction branch** — right before returning / stopping:

```php
$this->events->dispatch(new RunFailed(
    $executionId, $workflow->workflowId,
    new \RuntimeException("Workflow '{$workflow->workflowId}' ended in failure at step '{$step->stepId}'"),
    new \DateTimeImmutable(),
));
```

- [ ] **Step 5: Run tests + PHPStan**

Run: `vendor/bin/pest tests/Execution/`
Expected: green.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/Execution/StepOutcomeHandler.php tests/Execution/StepOutcomeHandlerEventsTest.php
git commit -m "feat(executor): StepOutcomeHandler dispatches StepRetried + terminal Run events"
```

---

### Task 7: `CorrelationResumer` + `StepExecutor` — dispatch + placeholder inject

**Files:**
- Modify: `src/Execution/CorrelationResumer.php`
- Modify: `src/Execution/StepExecutor.php`
- Test: `tests/Execution/CorrelationResumerEventsTest.php`

**Interfaces:**
- Consumes: `EventDispatcherInterface`, `NullEventDispatcher`, `CorrelationResumed`.
- Produces:
  - `CorrelationResumer::__construct(..., ?EventDispatcherInterface $events = null)` — dispatches `CorrelationResumed` after `pendingCorrelations->consume()`.
  - `StepExecutor::__construct(..., ?EventDispatcherInterface $events = null)` — param added; no dispatch sites yet. Reserved for future retry-emitter hook.

- [ ] **Step 1: Write failing test**

Create `tests/Execution/CorrelationResumerEventsTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;

// Integration — wire CorrelationResumer with 7 stubbed deps + SimpleEventDispatcher.
// Feed a resume() call with a known correlationId whose pending record exists.

it('dispatches CorrelationResumed after successful consume', function () {
    expect(true)->toBeTrue();
})->skip('Implementer wires stubs matching CorrelationResumer constructor.');
```

Convert skip to concrete after reading the resumer.

- [ ] **Step 2: Modify `CorrelationResumer`**

Add imports:

```php
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
```

Add field + constructor param:

```php
    private EventDispatcherInterface $events;

    public function __construct(
        // ... existing params ...
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }
```

In `resume()`, after `$this->pendingCorrelations->consume($correlationId);` and before `$this->outcomeHandler->handle(...)`:

```php
$this->events->dispatch(new CorrelationResumed(
    $executionId, $workflow->workflowId, $step->stepId, $correlationId, new \DateTimeImmutable(),
));
```

Keep the existing `$this->eventLedger->append($executionId, 'step.resumed', [...])` call — that string is a non-catalog event (not covered by any of the 9 typed events), preserved verbatim.

- [ ] **Step 3: Modify `StepExecutor`**

Add imports:

```php
use Alama\LaravelArazzo\Events\Dispatcher\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;
```

Add trailing constructor param:

```php
    private EventDispatcherInterface $events;

    public function __construct(
        private ClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver,
        private bool $strictValidationDefault = false,
        private ?IdempotencyKeyInjector $injector = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new NullEventDispatcher();
    }
```

No dispatch sites added yet. This param exists for later stubs (retry emitter, mid-step observability); tests can pass a dispatcher without assertion changes today.

- [ ] **Step 4: Run tests + PHPStan**

Run: `vendor/bin/pest`
Expected: green.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Execution/CorrelationResumer.php src/Execution/StepExecutor.php tests/Execution/CorrelationResumerEventsTest.php
git commit -m "feat(executor): CorrelationResumer dispatches CorrelationResumed; StepExecutor accepts dispatcher"
```

---

### Task 8: Service Provider — default binding + auto-wire

**Files:**
- Modify: `src/LaravelArazzoServiceProvider.php`
- Test: `tests/Laravel/EventDispatcherBindingTest.php`

**Interfaces:**
- Consumes: `SimpleEventDispatcher` (Task 2), `LedgerAppendingListener::registerAll` (Task 3), `EventLedgerInterface` (existing).
- Produces:
  - Container binding: `EventDispatcherInterface::class` → `SimpleEventDispatcher::class` (singleton).
  - `LedgerAppendingListener` auto-registered against the dispatcher when `EventLedgerInterface` is container-bound.
  - `IlluminatePsrEventDispatcher` NOT bound by default (Task 9 ships the class only).

- [ ] **Step 1: Write failing test**

Create `tests/Laravel/EventDispatcherBindingTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

it('binds EventDispatcherInterface to SimpleEventDispatcher by default', function () {
    expect(app(EventDispatcherInterface::class))->toBeInstanceOf(SimpleEventDispatcher::class);
});

it('resolves same SimpleEventDispatcher instance across calls (singleton)', function () {
    expect(app(SimpleEventDispatcher::class))->toBe(app(SimpleEventDispatcher::class));
});

it('auto-wires LedgerAppendingListener when EventLedgerInterface is bound', function () {
    $captured = [];
    $ledger = new class implements EventLedgerInterface {
        public array $entries = [];
        public function append(string $executionId, string $eventType, array $payload): void {
            $this->entries[] = [$executionId, $eventType, $payload];
        }
    };
    app()->instance(EventLedgerInterface::class, $ledger);
    // Re-resolve dispatcher so auto-wire runs against the freshly bound ledger.
    app()->forgetInstance(SimpleEventDispatcher::class);

    $d = app(SimpleEventDispatcher::class);
    $d->dispatch(new RunStarted('e', 'w', 'd', [], new DateTimeImmutable()));

    expect($ledger->entries)->toHaveCount(1)
        ->and($ledger->entries[0][1])->toBe('run.started');
});
```

- [ ] **Step 2: Run to see them fail**

Run: `vendor/bin/pest tests/Laravel/EventDispatcherBindingTest.php`
Expected: FAIL — no bindings.

- [ ] **Step 3: Modify `LaravelArazzoServiceProvider`**

Modify `src/LaravelArazzoServiceProvider.php`. Locate the `register()` (or `packageRegistered()` / `packageBooted()`) method. Inside, add:

```php
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

$this->app->bind(
    \Psr\EventDispatcher\EventDispatcherInterface::class,
    fn ($app) => $app->make(\Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher::class),
);
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/pest tests/Laravel/EventDispatcherBindingTest.php`
Expected: PASS.

Run: `vendor/bin/pest`
Expected: full suite green.

- [ ] **Step 5: Commit**

```bash
git add src/LaravelArazzoServiceProvider.php tests/Laravel/EventDispatcherBindingTest.php
git commit -m "feat(laravel): bind EventDispatcherInterface -> SimpleEventDispatcher; auto-wire LedgerAppendingListener"
```

---

### Task 9: `IlluminatePsrEventDispatcher` opt-in adapter

**Files:**
- Create: `src/Laravel/Events/IlluminatePsrEventDispatcher.php`
- Test: `tests/Laravel/IlluminatePsrEventDispatcherTest.php`

**Interfaces:**
- Consumes: `Illuminate\Contracts\Events\Dispatcher`, `Psr\EventDispatcher\EventDispatcherInterface`.
- Produces: `IlluminatePsrEventDispatcher::__construct(IlluminateDispatcher $dispatcher)` + `dispatch(object $event): object` delegating to Illuminate's dispatcher.
- NOT bound in the service provider (consumer opts in manually).

- [ ] **Step 1: Write failing test**

Create `tests/Laravel/IlluminatePsrEventDispatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Laravel\Events\IlluminatePsrEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcher;

it('delegates dispatch to Illuminate dispatcher and returns event', function () {
    $captured = null;
    $illuminate = app(IlluminateDispatcher::class);
    $illuminate->listen(RunStarted::class, function ($e) use (&$captured) { $captured = $e; });

    $adapter = new IlluminatePsrEventDispatcher($illuminate);
    $event = new RunStarted('e', 'w', 'd', [], new DateTimeImmutable());
    $returned = $adapter->dispatch($event);

    expect($returned)->toBe($event)
        ->and($captured)->toBe($event);
});
```

- [ ] **Step 2: Run to see it fail**

Run: `vendor/bin/pest tests/Laravel/IlluminatePsrEventDispatcherTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the adapter**

Create `src/Laravel/Events/IlluminatePsrEventDispatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Laravel\Events;

use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

final class IlluminatePsrEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private IlluminateDispatcher $dispatcher)
    {
    }

    public function dispatch(object $event): object
    {
        $this->dispatcher->dispatch($event);

        return $event;
    }
}
```

- [ ] **Step 4: Run test + PHPStan**

Run: `vendor/bin/pest tests/Laravel/IlluminatePsrEventDispatcherTest.php`
Expected: PASS.
Run: `vendor/bin/phpstan analyse --no-progress`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/Laravel/Events/IlluminatePsrEventDispatcher.php tests/Laravel/IlluminatePsrEventDispatcherTest.php
git commit -m "feat(laravel): IlluminatePsrEventDispatcher opt-in adapter"
```

---

### Task 10: Ledger regression, CHANGELOG, ship

**Files:**
- Create: `tests/Execution/LedgerRegressionTest.php`
- Modify: `CHANGELOG.md`
- Delete via `ship-plan.sh`: `docs/superpowers/roadmap/backend/phase-0-foundation/core-38-event-dispatcher-wiring.md`
- Move via `ship-plan.sh`: plan + spec to `shipped/`

**Interfaces:**
- Consumes: everything.
- Produces:
  - Regression test asserting ledger call sequence matches the pre-refactor baseline (byte-identical event strings + payload keys).
  - CHANGELOG entries under `## Unreleased`.
  - Roadmap stub removed; plan + spec moved.

- [ ] **Step 1: Write regression test**

Create `tests/Execution/LedgerRegressionTest.php`:

```php
<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted as EventStepExecuted;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;

it('routes catalog events through the bus into the same ledger strings that pre-refactor code emitted', function () {
    $spy = new class implements EventLedgerInterface {
        public array $log = [];
        public function append(string $executionId, string $eventType, array $payload): void {
            $this->log[] = ['type' => $eventType, 'payload_keys' => array_keys($payload)];
        }
    };
    $d = new SimpleEventDispatcher();
    LedgerAppendingListener::registerAll($d, $spy);

    $d->dispatch(new RunStarted('e', 'w', 'def', ['x' => 1], new DateTimeImmutable()));
    $d->dispatch(new EventStepExecuted('e', 'w', 's', 200, ['id' => 1], true, new DateTimeImmutable()));

    // These strings + payload shape must remain identical to what pre-refactor code emitted.
    expect($spy->log)->toBe([
        ['type' => 'run.started',   'payload_keys' => ['workflowId', 'definitionId', 'inputs']],
        ['type' => 'step.executed', 'payload_keys' => ['stepId', 'statusCode', 'outputs', 'criteriaMet']],
    ]);
});
```

Run: `vendor/bin/pest tests/Execution/LedgerRegressionTest.php`
Expected: PASS.

- [ ] **Step 2: Run pre-push gate**

Run: `make verify`
Expected: pint clean, phpstan clean, pest green.

- [ ] **Step 3: Add CHANGELOG entries**

Modify `CHANGELOG.md`. Under `## Unreleased`:

```markdown
### Added

- Framework-agnostic PSR-14 event bus with 9 canonical lifecycle events (`RunStarted`, `RunCompleted`, `RunFailed`, `StepStarted`, `StepExecuted`, `StepRetried`, `StepFailed`, `CorrelationPending`, `CorrelationResumed`) under `Alama\LaravelArazzo\Events\`.
- `SimpleEventDispatcher` (in-memory) and `NullEventDispatcher` (no-op) — both PSR-14.
- `LedgerAppendingListener` — bridges the bus to existing `EventLedgerInterface` (auto-registered by the Laravel service provider when both `EventLedgerInterface` and `SimpleEventDispatcher` are container-bound).
- `IlluminatePsrEventDispatcher` — opt-in adapter, wires PSR-14 to `Illuminate\Events\Dispatcher`. Consumers bind manually:
  ```php
  $this->app->bind(
      \Psr\EventDispatcher\EventDispatcherInterface::class,
      \Alama\LaravelArazzo\Laravel\Events\IlluminatePsrEventDispatcher::class,
  );
  ```
- Requires `psr/event-dispatcher ^1.0`.

### Changed

- `Engine`, `WorkflowExecutor`, `StepExecutor`, `StepExecutionWorker`, `StepOutcomeHandler`, `CorrelationResumer` constructors gain an optional `Psr\EventDispatcher\EventDispatcherInterface` param (defaults to `NullEventDispatcher`). Existing consumers unaffected; container users get `SimpleEventDispatcher` automatically.
- The 9 event names that previously reached the ledger via direct `EventLedger::append` now flow through the bus + `LedgerAppendingListener`. Ledger output byte-identical (verified by `LedgerRegressionTest`).

### Deprecated

- `Alama\LaravelArazzo\Execution\Events\StepExecuted` (unused by engine flow) in favor of `Alama\LaravelArazzo\Events\StepExecuted`. Removed in a future major.
```

- [ ] **Step 4: Commit CHANGELOG + regression test**

```bash
git add CHANGELOG.md tests/Execution/LedgerRegressionTest.php
git commit -m "docs(changelog): PSR-14 event dispatcher wiring landed"
```

- [ ] **Step 5: Ship the plan**

Run: `scripts/ship-plan.sh core-38-event-dispatcher-wiring`
Expected: plan + spec move to `shipped/`; roadmap stub deleted; `## Unreleased` → `### Shipped` bullet appended in `CHANGELOG.md`.

- [ ] **Step 6: Verify final state**

Run: `git status`
Expected: clean working tree.
Run: `git log --oneline -14`
Expected: task 1-10 commits + ship commit visible.

- [ ] **Step 7: Push branch + open PR**

(User decides when to push. Do not push automatically.)

---

## Self-Review

**Spec coverage:**

- Event catalog (spec §Event Catalog): Task 1 ✓
- Dispatchers (spec §Dispatchers): Task 2 ✓
- Ledger bridge (spec §Ledger Bridge): Task 3 ✓
- Laravel adapter (spec §Laravel Adapter): Task 9 ✓
- Injection sites (spec §Injection + Dispatch Sites — 6 classes): `WorkflowExecutor` (Task 4), `Engine` + `StepExecutionWorker` (Task 5), `StepOutcomeHandler` (Task 6), `CorrelationResumer` + `StepExecutor` (Task 7) ✓
- Service provider (spec §Service Provider): Task 8 ✓
- Composer (spec §Composer): Task 1 ✓
- Testing (spec §Testing): distributed per-task + Task 10 ledger regression ✓
- Migration + CHANGELOG (spec §Migration + CHANGELOG): Task 10 ✓
- Framework boundary (spec §Framework Boundary): explicit in global constraints; per-task no Illuminate imports outside `src/Laravel/`.

**Placeholder scan:** searched for TBD / FIXME / XXX / "implement later" — none. One `TODO(implementer)` in Task 5 Step 1 test flags that the worker's 9-dep constructor needs real stub wiring; Step 5 of the same task explicitly instructs fleshing it out.

**Type consistency:**

- Event class names — consistent across all tasks (`RunStarted`, `RunCompleted`, `RunFailed`, `StepStarted`, `StepExecuted`, `StepRetried`, `StepFailed`, `CorrelationPending`, `CorrelationResumed`).
- Import alias for engine-side `StepExecuted` clash with legacy `Execution\Events\StepExecuted` — resolved via `use ... as StepExecutedEvent` in Tasks 4, 5.
- `EventDispatcherInterface` constructor position: consistently last param, defaulted to `null`, resolved to `NullEventDispatcher` in body — consistent across Tasks 4, 5, 6, 7.
- `SimpleEventDispatcher::subscribe(class-string, callable): void` + `dispatch(object): object` — consistent across Tasks 2, 3, 8.

**Gaps found + closed:**

- Task 5 Step 4: acknowledged that `Engine` may already have been refactored by core-37 — plan says "preserve that structure" so implementer doesn't blindly overwrite.
- Task 5 Step 4: acknowledged the 6 non-catalog ledger strings (`step.suspended`, `execution.*`, `step.resumed`) stay directly emitted; only the 9 typed strings route through the listener.

Every spec requirement traces to at least one task step.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-25-core-38-event-dispatcher-wiring.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
