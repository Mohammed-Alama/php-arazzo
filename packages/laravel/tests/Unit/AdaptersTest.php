<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Unit;

use Alama\Arazzo\Laravel\Queue\LaravelQueueDriver;
use Alama\Arazzo\Laravel\Tests\TestCase;
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
