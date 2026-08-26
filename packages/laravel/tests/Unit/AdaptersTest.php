<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Unit;

use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Illuminate\Support\Facades\Queue;

it('dispatches to the queue', function (): void {
    Queue::fake();

    $driver = new LaravelQueueDriver();
    $driver->dispatch(new \stdClass());

    Queue::assertPushed(\stdClass::class);
});
