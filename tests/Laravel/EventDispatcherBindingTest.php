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
    $d->dispatch(new RunStarted('e', 'w', 'd', [], new \DateTimeImmutable()));

    expect($ledger->entries)->toHaveCount(1)
        ->and($ledger->entries[0][1])->toBe('run.started');
});
