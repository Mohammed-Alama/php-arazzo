<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Interfaces\HttpClientInterface;
use Alama\Arazzo\Interfaces\LockManagerInterface;
use Alama\Arazzo\Interfaces\QueueDriverInterface;

it('has interfaces', function (): void {
    expect(interface_exists(QueueDriverInterface::class))->toBeTrue()
        ->and(interface_exists(LockManagerInterface::class))->toBeTrue()
        ->and(interface_exists(HttpClientInterface::class))->toBeTrue();
});
