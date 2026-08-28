<?php

declare(strict_types=1);

use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

it('binds EventDispatcherInterface to SimpleEventDispatcher by default', function () {
    expect(app(EventDispatcherInterface::class))->toBeInstanceOf(SimpleEventDispatcher::class);
});

it('resolves same SimpleEventDispatcher instance across calls (singleton)', function () {
    expect(app(SimpleEventDispatcher::class))->toBe(app(SimpleEventDispatcher::class));
});

it('auto-wires LedgerEventListener when EventLedgerInterface is bound', function () {
    $ledger = new class() implements EventLedgerInterface
    {
        public array $entries = [];

        public function append(string $executionId, string $eventType, array $payload): void
        {
            $this->entries[] = [$executionId, $eventType, $payload];
        }
    };
    app()->instance(EventLedgerInterface::class, $ledger);
    // Re-resolve dispatcher so auto-wire runs against the freshly bound ledger.
    app()->forgetInstance(SimpleEventDispatcher::class);

    $d = app(SimpleEventDispatcher::class);
    $d->dispatch(new RunStartedEvent('e', 'w', 'd', [], new DateTimeImmutable()));

    expect($ledger->entries)->toHaveCount(1)
        ->and($ledger->entries[0][1])->toBe('run.started');
});
