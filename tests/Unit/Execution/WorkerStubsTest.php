<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Events\StepExecuted;
use PHPUnit\Framework\TestCase;

class WorkerStubsTest extends TestCase
{
    public function test_interfaces_and_events_exist(): void
    {
        $this->assertTrue(interface_exists(ExpressionResolverInterface::class));
        $this->assertTrue(class_exists(StepExecuted::class));
    }
}
