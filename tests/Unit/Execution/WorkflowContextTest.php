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
    public function test_with_step_request_is_immutable_and_merges_into_steps(): void
    {
        $context = new WorkflowContext('def_1');
        $newContext = $context->withStepRequest('step_1', ['method' => 'GET', 'url' => 'http://x']);

        $this->assertNotSame($context, $newContext);
        $this->assertEmpty($context->getSteps());
        $this->assertEquals(['method' => 'GET', 'url' => 'http://x'], $newContext->getSteps()['step_1']['request']);
    }

    public function test_with_step_response_merges_alongside_existing_request(): void
    {
        $context = (new WorkflowContext('def_1'))
            ->withStepRequest('step_1', ['method' => 'GET'])
            ->withStepResponse('step_1', ['statusCode' => 200]);

        $this->assertEquals(['method' => 'GET'], $context->getSteps()['step_1']['request']);
        $this->assertEquals(['statusCode' => 200], $context->getSteps()['step_1']['response']);
    }

    public function test_with_step_output_merges_individual_keys(): void
    {
        $context = (new WorkflowContext('def_1'))
            ->withStepOutput('step_1', 'id', 123)
            ->withStepOutput('step_1', 'name', 'Alice');

        $this->assertEquals(['id' => 123, 'name' => 'Alice'], $context->getSteps()['step_1']['outputs']);
    }
}
