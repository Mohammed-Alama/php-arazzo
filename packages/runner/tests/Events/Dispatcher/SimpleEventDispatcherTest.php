<?php

declare(strict_types=1);

use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Psr\EventDispatcher\StoppableEventInterface;

it('delivers to subscribed listeners in subscription order', function () {
    $log = [];
    $d = new SimpleEventDispatcher();
    $d->subscribe(RunStartedEvent::class, function ($e) use (&$log) {
        $log[] = 'a:'.$e->executionId;
    });
    $d->subscribe(RunStartedEvent::class, function ($e) use (&$log) {
        $log[] = 'b:'.$e->executionId;
    });

    $event = new RunStartedEvent('exec-1', 'w', 'd', [], new DateTimeImmutable());
    $d->dispatch($event);

    expect($log)->toBe(['a:exec-1', 'b:exec-1']);
});

it('returns the event object from dispatch', function () {
    $d = new SimpleEventDispatcher();
    $event = new RunStartedEvent('exec-1', 'w', 'd', [], new DateTimeImmutable());
    expect($d->dispatch($event))->toBe($event);
});

it('is a no-op for events with no subscribers', function () {
    $d = new SimpleEventDispatcher();
    $event = new RunStartedEvent('exec-1', 'w', 'd', [], new DateTimeImmutable());
    expect($d->dispatch($event))->toBe($event); // does not throw
});

it('matches listeners registered for parent class or interface', function () {
    $captured = null;
    $d = new SimpleEventDispatcher();
    $d->subscribe(stdClass::class, function ($e) use (&$captured) {
        $captured = $e;
    });

    $event = new class() extends stdClass {};
    $d->dispatch($event);

    expect($captured)->toBe($event);
});

it('respects StoppableEventInterface propagation', function () {
    $log = [];
    $d = new SimpleEventDispatcher();

    $stoppable = new class() implements StoppableEventInterface
    {
        public bool $stopped = false;

        public function isPropagationStopped(): bool
        {
            return $this->stopped;
        }
    };

    $d->subscribe($stoppable::class, function ($e) use (&$log) {
        $log[] = 'first';
        $e->stopped = true;
    });
    $d->subscribe($stoppable::class, function ($e) use (&$log) {
        $log[] = 'second';
    });

    $d->dispatch($stoppable);
    expect($log)->toBe(['first']);
});
