<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Events\IlluminatePsrEventDispatcher;
use Alama\Arazzo\Runner\Events\RunStartedEvent;
use Illuminate\Contracts\Events\Dispatcher as IlluminateDispatcher;

it('delegates dispatch to Illuminate dispatcher and returns event', function () {
    $captured = null;
    $illuminate = app(IlluminateDispatcher::class);
    $illuminate->listen(RunStartedEvent::class, function ($e) use (&$captured) {
        $captured = $e;
    });

    $adapter = new IlluminatePsrEventDispatcher($illuminate);
    $event = new RunStartedEvent('e', 'w', 'd', [], new DateTimeImmutable());
    $returned = $adapter->dispatch($event);

    expect($returned)->toBe($event)
        ->and($captured)->toBe($event);
});
