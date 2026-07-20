<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use PHPUnit\Framework\TestCase;

class DependencyAnalyzerTest extends TestCase
{
    public function test_finds_runnable_steps(): void
    {
        $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
        
        $analyzer = new DependencyAnalyzer();
        
        // Initial state
        $context = new WorkflowContext('def_1');
        $runnable = $analyzer->getRunnableSteps([$stepA, $stepB], $context);
        $this->assertCount(1, $runnable);
        $this->assertEquals('A', $runnable[0]->stepId);

        // State after A completes
        $context2 = $context->withStepResult('A', ['outputs' => []]);
        $runnable2 = $analyzer->getRunnableSteps([$stepA, $stepB], $context2);
        $this->assertCount(1, $runnable2);
        $this->assertEquals('B', $runnable2[0]->stepId);
    }
}
