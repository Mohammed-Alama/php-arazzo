<?php

declare(strict_types=1);

use Alama\Arazzo\Async\ExecutionStateBuilder;
use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;

function builderStep(string $id): Step
{
    return new Step($id, null, null, null, null, [], null, [], [], [], []);
}

function builderWorkflow(): Workflow
{
    return new Workflow('wf', null, null, null, [], [builderStep('a'), builderStep('b')], [], [], [], []);
}

it('builds a fresh state from the result context when nothing is persisted', function (): void {
    $context = (new WorkflowContext('def', ['in' => 1]))->withExecutionId('exec_b');

    $state = (new ExecutionStateBuilder())->build(null, $context, builderWorkflow());

    expect($state->executionId)->toBe('exec_b')
        ->and($state->definitionId)->toBe('def')
        ->and($state->workflowId)->toBe('wf')
        ->and($state->inputs['in'])->toBe(1);
});

it('seeds from the persisted payload so budget and stack survive job boundaries', function (): void {
    // State-store payloads are CONTEXT-shaped: attempt counters live inside
    // each step record ('attempts' = 1-based number of the attempt that just
    // ran); the engine must decide on PREVIOUS attempts.
    $persisted = [
        'executionId' => 'exec_p',
        'definitionId' => 'def',
        'workflowId' => 'wf',
        'steps' => ['done' => ['statusCode' => 200, 'attempts' => 3]],
        'inputs' => [],
        'components' => [],
        'stepsSpent' => 9,
        'workflowCallStack' => ['parent', 'wf'],
    ];
    // Post-reconcile, the result context mirrors the persisted record AND
    // carries its budget/call-stack (that is what StateReconciler produces).
    $context = (new WorkflowContext('def'))
        ->withExecutionId('exec_p')
        ->withStepResult('done', ['statusCode' => 200, 'attempts' => 3])
        ->withBudget(9, ['parent', 'wf']);

    $state = (new ExecutionStateBuilder())->build($persisted, $context, builderWorkflow());

    expect($state->attemptFor('done'))->toBe(2)
        ->and($state->stepsSpent)->toBe(9)
        ->and($state->workflowCallStack)->toBe(['parent', 'wf'])
        ->and($state->stepResults['done']['statusCode'])->toBe(200);
});

it('defaults an empty call stack to the current workflow', function (): void {
    $context = (new WorkflowContext('def'))->withExecutionId('x');

    $state = (new ExecutionStateBuilder())->build(null, $context, builderWorkflow());

    expect($state->workflowCallStack)->toBe(['wf']);
});

it('normalizes non-string-keyed junk in context records instead of crashing', function (): void {
    $context = (new WorkflowContext('def'))
        ->withExecutionId('junk')
        ->withStepResult('weird', [0 => 'positional', 'ok' => 'kept']);

    $state = (new ExecutionStateBuilder())->build(null, $context, builderWorkflow());

    expect($state->stepResults['weird'])->toBe(['ok' => 'kept']);
});

it('overlays the just-finished step record after attempt replay', function (): void {
    $persisted = [
        'executionId' => 'ov',
        'workflowId' => 'wf',
        'definitionId' => 'def',
        'steps' => ['b' => ['attempts' => 3, 'stale' => true]],
        'inputs' => [],
        'components' => [],
    ];
    $context = (new WorkflowContext('def'))->withExecutionId('ov');
    $context = $context->withStepResult('b', ['fresh' => true]);

    $state = (new ExecutionStateBuilder())->build($persisted, $context, builderWorkflow(), builderStep('b'));

    expect($state->stepResults['b'])->toBe(['fresh' => true]);
});
