<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class AdaptersTest extends TestCase
{
    public function test_queue_dispatch(): void
    {
        Queue::fake();
        $driver = new LaravelQueueDriver();
        $job = new \stdClass();
        $driver->dispatch($job);
        Queue::assertPushed(\stdClass::class);
    }
}
