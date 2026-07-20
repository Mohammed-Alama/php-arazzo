<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\WorkflowContext;

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

it('merges withStepResponse alongside an existing request', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200]);

    expect($context->getSteps()['step_1']['request'])->toEqual(['method' => 'GET']);
    expect($context->getSteps()['step_1']['response'])->toEqual(['statusCode' => 200]);
});

it('merges withStepOutput as individual keys', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withStepOutput('step_1', 'id', 123)
        ->withStepOutput('step_1', 'name', 'Alice');

    expect($context->getSteps()['step_1']['outputs'])->toEqual(['id' => 123, 'name' => 'Alice']);
});

it('defaults workflowId and executionId to null', function (): void {
    $context = new WorkflowContext('def_1');

    expect($context->getWorkflowId())->toBeNull();
    expect($context->getExecutionId())->toBeNull();
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
    expect($context->getExecutionId())->toBeNull();
    expect($newContext->getExecutionId())->toBe('exec_1');
});

it('carries workflowId and executionId through every step mutator', function (): void {
    $context = (new WorkflowContext('def_1'))
        ->withWorkflowId('wf_1')
        ->withExecutionId('exec_1')
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['statusCode' => 200])
        ->withStepOutput('step_1', 'id', 1)
        ->withStepResult('step_2', ['done' => true]);

    expect($context->getWorkflowId())->toBe('wf_1');
    expect($context->getExecutionId())->toBe('exec_1');
});
