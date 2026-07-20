<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use PHPUnit\Framework\TestCase;

class SyncQueueDriverTest extends TestCase
{
    public function test_dispatch_records_single_job_with_delay(): void
    {
        $driver = new SyncQueueDriver();
        $job = new \stdClass();
        $delaySeconds = 5;

        $driver->dispatch($job, $delaySeconds);

        $this->assertCount(1, $driver->dispatched);
        $this->assertSame($job, $driver->dispatched[0]['job']);
        $this->assertSame(5, $driver->dispatched[0]['delaySeconds']);
    }

    public function test_dispatch_records_multiple_jobs_in_order(): void
    {
        $driver = new SyncQueueDriver();
        $job1 = new \stdClass();
        $job2 = new \stdClass();
        $job3 = new \stdClass();

        $driver->dispatch($job1, 0);
        $driver->dispatch($job2, 10);
        $driver->dispatch($job3, 20);

        $this->assertCount(3, $driver->dispatched);
        $this->assertSame($job1, $driver->dispatched[0]['job']);
        $this->assertSame(0, $driver->dispatched[0]['delaySeconds']);
        $this->assertSame($job2, $driver->dispatched[1]['job']);
        $this->assertSame(10, $driver->dispatched[1]['delaySeconds']);
        $this->assertSame($job3, $driver->dispatched[2]['job']);
        $this->assertSame(20, $driver->dispatched[2]['delaySeconds']);
    }
}
