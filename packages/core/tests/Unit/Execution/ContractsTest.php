<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Runner\Execution\Contracts\HttpClientInterface;
use Alama\Arazzo\Runner\Execution\Contracts\LockManagerInterface;
use Alama\Arazzo\Runner\Execution\Contracts\QueueDriverInterface;
use PHPUnit\Framework\TestCase;

class ContractsTest extends TestCase
{
    public function test_interfaces_exist(): void
    {
        $this->assertTrue(interface_exists(QueueDriverInterface::class));
        $this->assertTrue(interface_exists(LockManagerInterface::class));
        $this->assertTrue(interface_exists(HttpClientInterface::class));
    }
}
