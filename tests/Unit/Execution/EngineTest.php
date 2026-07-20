<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use PHPUnit\Framework\TestCase;

class MockQueueDriver implements QueueDriverInterface
{
    public array $dispatched = [];

    public function dispatch(object $job, int $delaySeconds = 0): void
    {
        $this->dispatched[] = $job;
    }
}

class MockStateStore implements StateStoreInterface
{
    public function save(string $id, array $state): void
    {
    }

    public function load(string $id): array
    {
        return [];
    }
}

class EngineTest extends TestCase
{
    public function test_engine_dispatches_runnable_steps(): void
    {
        $queue = new MockQueueDriver();
        $store = new MockStateStore();
        $analyzer = new DependencyAnalyzer();
        $engine = new Engine($analyzer, $queue, $store);

        $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('w_1', null, null, [], [], [$stepA, $stepB], [], [], [], []);

        $context = new WorkflowContext('def_1');

        $engine->evaluate($workflow, $context);

        $this->assertCount(2, $queue->dispatched);
    }
}
