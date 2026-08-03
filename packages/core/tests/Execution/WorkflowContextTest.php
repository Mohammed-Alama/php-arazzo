<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Execution\StepStatus;
use Alama\Arazzo\Execution\WorkflowContext;

it('is immutable on withStepResult', function (): void {
    $context = new WorkflowContext('def_1', ['id' => 1]);
    $newContext = $context->withStepResult('step_1', ['success' => true]);

    expect($newContext)->not->toBe($context);
    expect($context->getSteps())->toBeEmpty();
    expect($newContext->getSteps()['step_1'])->toEqual(['success' => true]);
    expect($newContext->getDefinitionId())->toBe('def_1');
});

it('is immutable on withStepRequest and merges into steps', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withStepRequest('step_1', ['method' => 'GET', 'url' => 'http://x']);

    expect($newContext)->not->toBe($context);
    expect($context->getSteps())->toBeEmpty();
    expect($newContext->getSteps()['step_1']['request'])->toEqual(['method' => 'GET', 'url' => 'http://x']);
});

it('is immutable on withStepResponse and merges alongside an existing request', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step_1', ['method' => 'GET']);
    $newContext = $context->withStepResponse('step_1', ['statusCode' => 200]);

    expect($newContext)->not->toBe($context);
    expect($newContext->getSteps()['step_1']['request'])->toEqual(['method' => 'GET']);
    expect($newContext->getSteps()['step_1']['response'])->toEqual(['statusCode' => 200]);
});

it('is immutable on withStepOutput and merges as individual keys', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepOutput('step_1', 'id', 123);
    $newContext = $context->withStepOutput('step_1', 'name', 'Alice');

    expect($newContext)->not->toBe($context);
    expect($newContext->getSteps()['step_1']['outputs'])->toEqual(['id' => 123, 'name' => 'Alice']);
});

it('defaults workflowId to null and executionId to a UUID', function (): void {
    $context = new WorkflowContext('def_1');

    expect($context->getWorkflowId())->toBeNull();
    expect($context->getExecutionId())->not->toBeNull();
});

it('is immutable on withWorkflowId', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withWorkflowId('wf_1');

    expect($newContext)->not->toBe($context);
    expect($context->getWorkflowId())->toBeNull();
    expect($newContext->getWorkflowId())->toBe('wf_1');
});

it('is immutable on withExecutionId', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withExecutionId('exec_1');

    expect($newContext)->not->toBe($context);
    expect($context->getExecutionId())->not->toBe('exec_1');
    expect($newContext->getExecutionId())->toBe('exec_1');
});

it('carries workflowId and executionId through every step mutator', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200])
        ->withStepOutput('step_1', 'id', 1)
        ->withStepStatus('step_1', StepStatus::Pending)
        ->withStepAttemptIncremented('step_1')
        ->withStepResult('step_2', ['done' => true]);

    expect($context->getWorkflowId())->toBe('wf_1');
    expect($context->getExecutionId())->toBe('exec_1');
});

it('defaults step status to null and attempts to 0', function (): void {
    $context = new WorkflowContext('def_1');

    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($context->getStepAttempts('step_1'))->toBe(0);
});

it('is immutable on withStepStatus', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withStepStatus('step_1', StepStatus::Pending);

    expect($newContext)->not->toBe($context);
    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($newContext->getStepStatus('step_1'))->toBe(StepStatus::Pending);
});

it('is immutable on withStepAttemptIncremented', function (): void {
    $context = new WorkflowContext('def_1');
    $newContext = $context->withStepAttemptIncremented('step_1');

    expect($newContext)->not->toBe($context);
    expect($context->getStepAttempts('step_1'))->toBe(0);
    expect($newContext->getStepAttempts('step_1'))->toBe(1);

    $newContext2 = $newContext->withStepAttemptIncremented('step_1');
    expect($newContext2->getStepAttempts('step_1'))->toBe(2);
});

it('retains status and attempts when request/response/outputs are added', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepStatus('step_1', StepStatus::Pending)
        ->withStepAttemptIncremented('step_1')
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200])
        ->withStepOutput('step_1', 'id', 1);

    expect($context->getStepStatus('step_1'))->toBe(StepStatus::Pending);
    expect($context->getStepAttempts('step_1'))->toBe(1);
});

it('resets status and attempts when withStepResult overwrites', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepStatus('step_1', StepStatus::Pending)
        ->withStepAttemptIncremented('step_1');

    expect($context->getStepStatus('step_1'))->toBe(StepStatus::Pending);
    expect($context->getStepAttempts('step_1'))->toBe(1);

    $context = $context->withStepResult('step_1', ['done' => true]);

    expect($context->getStepStatus('step_1'))->toBeNull();
    expect($context->getStepAttempts('step_1'))->toBe(0);
});

it('can transition through multiple statuses', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepStatus('step_1', StepStatus::Pending)
        ->withStepStatus('step_1', StepStatus::Retrying)
        ->withStepStatus('step_1', StepStatus::Failed);

    expect($context->getStepStatus('step_1'))->toBe(StepStatus::Failed);
});
