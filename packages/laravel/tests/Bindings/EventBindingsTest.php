<?php

declare(strict_types=1);

use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Laravel\Bindings\EventBindings;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * In-memory ledger fake: records appends so listener wiring is observable
 * without touching the database.
 */
final class RecordingLedger implements EventLedgerInterface
{
    /** @var list<array{executionId: string, eventType: string}> */
    public array $appended = [];

    public function append(string $executionId, string $eventType, array $payload = []): void
    {
        $this->appended[] = ['executionId' => $executionId, 'eventType' => $eventType];
    }
}

it('binds the psr-14 alias to the package dispatcher singleton', function (): void {
    expect(app(EventDispatcherInterface::class))->toBeInstanceOf(SimpleEventDispatcher::class)
        ->and(app(EventDispatcherInterface::class))->toBe(app(SimpleEventDispatcher::class));
});

it('bridges domain events into the durable ledger through the registered listener', function (): void {
    $ledger = new RecordingLedger();
    $this->app->instance(EventLedgerInterface::class, $ledger);

    // Re-run the binding so a fresh dispatcher picks up the swapped ledger.
    app()->forgetInstance(SimpleEventDispatcher::class);
    EventBindings::register($this->app);

    /** @var SimpleEventDispatcher $dispatcher */
    $dispatcher = app(EventDispatcherInterface::class);
    $dispatcher->dispatch(new RunStartedEvent('exec_bindings_1', 'wf', 'wf', [], new DateTimeImmutable()));

    expect($ledger->appended)->not->toBeEmpty()
        ->and($ledger->appended[0]['executionId'])->toBe('exec_bindings_1');
});

it('does not duplicate listeners when the binding is re-registered', function (): void {
    $ledger = new RecordingLedger();
    $this->app->instance(EventLedgerInterface::class, $ledger);

    // Same singleton survives re-registration, so listeners must not stack.
    EventBindings::register($this->app);
    EventBindings::register($this->app);

    /** @var SimpleEventDispatcher $dispatcher */
    $dispatcher = app(SimpleEventDispatcher::class);
    $dispatcher->dispatch(new RunStartedEvent('exec_dupe_1', 'wf', 'wf', [], new DateTimeImmutable()));

    $hits = count(array_filter(
        $ledger->appended,
        fn (array $a) => $a['executionId'] === 'exec_dupe_1',
    ));

    expect($hits)->toBe(1);
});
