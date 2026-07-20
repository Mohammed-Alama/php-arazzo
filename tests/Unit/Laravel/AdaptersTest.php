<?php
namespace Tests\Unit\Laravel;

use Alama\LaravelArazzo\Laravel\LaravelQueueDriver;
use Alama\LaravelArazzo\Laravel\LaravelRedisLockManager;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Alama\LaravelArazzo\Tests\TestCase;

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
