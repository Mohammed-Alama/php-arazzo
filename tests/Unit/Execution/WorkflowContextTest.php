<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\WorkflowContext;
use PHPUnit\Framework\TestCase;

class WorkflowContextTest extends TestCase
{
    public function test_immutability(): void
    {
        $context = new WorkflowContext('def_1', ['id' => 1]);
        $newContext = $context->withStepResult('step_1', ['success' => true]);

        $this->assertNotSame($context, $newContext);
        $this->assertEmpty($context->getSteps());
        $this->assertEquals(['success' => true], $newContext->getSteps()['step_1']);
        $this->assertEquals('def_1', $newContext->getDefinitionId());
    }
}
