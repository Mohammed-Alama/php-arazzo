<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Events\StepExecuted;
use PHPUnit\Framework\TestCase;

class WorkerStubsTest extends TestCase
{
    public function test_interfaces_and_events_exist(): void
    {
        $this->assertTrue(interface_exists(ExpressionResolverInterface::class));
        $this->assertTrue(class_exists(StepExecuted::class));
    }
}
