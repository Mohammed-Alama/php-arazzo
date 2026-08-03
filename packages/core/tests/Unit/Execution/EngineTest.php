<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\Workflow;
use Alama\Arazzo\Execution\Contracts\QueueDriverInterface;
use Alama\Arazzo\Execution\Contracts\StateStoreInterface;
use Alama\Arazzo\Execution\Engine;
use Alama\Arazzo\Execution\Jobs\ExecuteStepJob;
use Alama\Arazzo\Execution\WorkflowContext;

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
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
    }

    public function load(string $executionId): ?array
    {
        return null;
    }
}

it('dispatches every runnable step', function (): void {
    $queue = new MockQueueDriver();
    $store = new MockStateStore();
    $engine = new Engine($queue, $store);

    $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
    $workflow = new Workflow('w_1', null, null, [], [], [$stepA, $stepB], [], [], [], []);

    $context = new WorkflowContext('def_1');

    $engine->evaluate($workflow, $context);

    expect($queue->dispatched)->toHaveCount(2);
});

it('stamps workflowId onto the dispatched job context', function (): void {
    $queue = new MockQueueDriver();
    $engine = new Engine($queue, new MockStateStore());

    $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $context = new WorkflowContext('def_1');

    $engine->evaluate($workflow, $context);

    expect($queue->dispatched)->toHaveCount(1);
    /** @var ExecuteStepJob $job */
    $job = $queue->dispatched[0];
    expect($job->context->getWorkflowId())->toBe('wf_1');
});

it('does not overwrite an already-set workflowId', function (): void {
    $queue = new MockQueueDriver();
    $engine = new Engine($queue, new MockStateStore());

    $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $context = (new WorkflowContext('def_1'))->withWorkflowId('wf_original');

    $engine->evaluate($workflow, $context);

    /** @var ExecuteStepJob $job */
    $job = $queue->dispatched[0];
    expect($job->context->getWorkflowId())->toBe('wf_original');
});
