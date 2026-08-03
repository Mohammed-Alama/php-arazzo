<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\Listener\LedgerAppendingListener;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted as EventStepExecuted;
use Alama\Arazzo\Execution\Contracts\EventLedgerInterface;

it('routes catalog events through the bus into the same ledger strings that pre-refactor code emitted', function () {
    $spy = new class() implements EventLedgerInterface
    {
        public array $log = [];

        public function append(string $executionId, string $eventType, array $payload): void
        {
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
