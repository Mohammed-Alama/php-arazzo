<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Execution\SyncQueueDriver;

it('dispatch records single job with delay', function (): void {
    $driver = new SyncQueueDriver();
    $job = new \stdClass();

    $driver->dispatch($job, 5);

    expect($driver->dispatched)->toHaveCount(1)
        ->and($driver->dispatched[0]['job'])->toBe($job)
        ->and($driver->dispatched[0]['delaySeconds'])->toBe(5);
});

it('dispatch records multiple jobs in order', function (): void {
    $driver = new SyncQueueDriver();
    $job1 = new \stdClass();
    $job2 = new \stdClass();
    $job3 = new \stdClass();

    $driver->dispatch($job1, 0);
    $driver->dispatch($job2, 10);
    $driver->dispatch($job3, 20);

    expect($driver->dispatched)->toHaveCount(3)
        ->and($driver->dispatched[0]['job'])->toBe($job1)
        ->and($driver->dispatched[0]['delaySeconds'])->toBe(0)
        ->and($driver->dispatched[1]['job'])->toBe($job2)
        ->and($driver->dispatched[1]['delaySeconds'])->toBe(10)
        ->and($driver->dispatched[2]['job'])->toBe($job3)
        ->and($driver->dispatched[2]['delaySeconds'])->toBe(20);
});
