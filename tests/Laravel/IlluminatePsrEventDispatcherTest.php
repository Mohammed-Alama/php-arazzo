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
    $event = new RunStarted('e', 'w', 'd', [], new \DateTimeImmutable());
    $returned = $adapter->dispatch($event);

    expect($returned)->toBe($event)
        ->and($captured)->toBe($event);
});
