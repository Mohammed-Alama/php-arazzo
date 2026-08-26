<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Runner\Execution\Contracts\HttpClientInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;

it('has interfaces', function (): void {
    expect(interface_exists(QueueDriverInterface::class))->toBeTrue()
        ->and(interface_exists(LockManagerInterface::class))->toBeTrue()
        ->and(interface_exists(HttpClientInterface::class))->toBeTrue();
});
