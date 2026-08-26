<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Events\CorrelationPending;
use Alama\Arazzo\Events\CorrelationResumed;
use Alama\Arazzo\Events\RunCompleted;
use Alama\Arazzo\Events\RunFailed;
use Alama\Arazzo\Events\RunStarted;
use Alama\Arazzo\Events\StepExecuted;
use Alama\Arazzo\Events\StepFailed;
use Alama\Arazzo\Events\StepRetried;
use Alama\Arazzo\Events\StepStarted;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Support\Events\Listener\LedgerAppendingListener;

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
