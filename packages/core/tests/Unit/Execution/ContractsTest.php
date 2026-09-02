<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;

it('has interfaces', function (): void {
    expect(interface_exists(QueueDriverInterface::class))->toBeTrue()
        ->and(interface_exists(LockManagerInterface::class))->toBeTrue()
        ->and(interface_exists(HttpClientInterface::class))->toBeTrue();
});
