<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Events\CorrelationPendingEvent;
use Alama\Arazzo\Events\CorrelationResumedEvent;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Events\Listener\LedgerEventListener;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepRetriedEvent;
use Alama\Arazzo\Events\StepStartedEvent;

class SpyLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, type: string, payload: array<string, mixed>}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload): void
    {
        $this->appended[] = ['executionId' => $executionId, 'type' => $eventType, 'payload' => $payload];
    }
}

function ledgerListener(): array
{
    $spy = new SpyLedger();

    return [$spy, new LedgerEventListener($spy)];
}

it('maps RunStartedEvent to run.started', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunStartedEvent('exec-1', 'w', 'def', ['k' => 1], new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'run.started',
        'payload' => ['workflowId' => 'w', 'definitionId' => 'def', 'inputs' => ['k' => 1]],
    ]]);
});

it('maps RunCompletedEvent to run.completed', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunCompletedEvent('exec-1', 'w', ['x' => 1], new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'run.completed',
        'payload' => ['workflowId' => 'w', 'outputs' => ['x' => 1]],
    ]]);
});

it('maps RunFailedEvent to run.failed', function () {
    [$spy, $l] = ledgerListener();
    $l(new RunFailedEvent('exec-1', 'w', new RuntimeException('boom'), new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'run.failed',
        'payload' => ['workflowId' => 'w', 'error' => ['class' => RuntimeException::class, 'message' => 'boom']],
    ]]);
});

it('maps StepStartedEvent to step.started', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepStartedEvent('exec-1', 'w', 's1', 1, new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'step.started',
        'payload' => ['stepId' => 's1', 'attempt' => 1],
    ]]);
});

it('maps StepExecutedEvent to step.executed', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepExecutedEvent('exec-1', 'w', 's1', 200, ['id' => 1], true, new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'step.executed',
        'payload' => ['stepId' => 's1', 'statusCode' => 200, 'outputs' => ['id' => 1], 'criteriaMet' => true],
    ]]);
});

it('maps StepRetriedEvent to step.retried', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepRetriedEvent('exec-1', 'w', 's1', 2, null, new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'step.retried',
        'payload' => ['stepId' => 's1', 'attempt' => 2, 'lastError' => null],
    ]]);
});

it('maps StepFailedEvent to step.failed', function () {
    [$spy, $l] = ledgerListener();
    $l(new StepFailedEvent('exec-1', 'w', 's1', new RuntimeException('bad'), new DateTimeImmutable(), 'criteria'));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'step.failed',
        'payload' => ['stepId' => 's1', 'error' => ['class' => RuntimeException::class, 'message' => 'bad']],
    ]]);
});

it('maps CorrelationPendingEvent to correlation.pending', function () {
    [$spy, $l] = ledgerListener();
    $l(new CorrelationPendingEvent('exec-1', 'w', 's1', 'corr-1', 'chan/path', new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'correlation.pending',
        'payload' => ['stepId' => 's1', 'correlationId' => 'corr-1', 'channelPath' => 'chan/path'],
    ]]);
});

it('maps CorrelationResumedEvent to correlation.resumed', function () {
    [$spy, $l] = ledgerListener();
    $l(new CorrelationResumedEvent('exec-1', 'w', 's1', 'corr-1', new DateTimeImmutable()));
    expect($spy->appended)->toBe([[
        'executionId' => 'exec-1', 'type' => 'correlation.resumed',
        'payload' => ['stepId' => 's1', 'correlationId' => 'corr-1'],
    ]]);
});

it('registers all event types with the dispatcher', function () {
    $d = new SimpleEventDispatcher();
    $spy = new SpyLedger();
    LedgerEventListener::registerAll($d, $spy);

    $d->dispatch(new RunStartedEvent('e', 'w', 'def', ['x' => 1], new DateTimeImmutable()));
    $d->dispatch(new StepExecutedEvent('e', 'w', 's', 200, ['id' => 1], true, new DateTimeImmutable()));
    $d->dispatch(new StepFailedEvent('e', 'w', 's', new RuntimeException('bad'), new DateTimeImmutable(), 'criteria'));

    expect($spy->appended)->toHaveCount(3);
});
