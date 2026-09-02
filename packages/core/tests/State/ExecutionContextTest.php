<?php

declare(strict_types=1);

use Alama\Arazzo\Execution\Data\WorkflowContext;
use Alama\Arazzo\State\Budget;
use Alama\Arazzo\State\ErrorEntry;
use Alama\Arazzo\State\ExecutionContext;
use Alama\Arazzo\State\ExecutionState;
use Alama\Arazzo\State\StepResult;

it('starts with a seeded call stack and running status', function (): void {
    $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1', inputs: ['a' => 1], maxSteps: 50);

    expect($context->executionId)->toBe('exec_1')
        ->and($context->workflowId)->toBe('wf_1')
        ->and($context->getWorkflowCallStack())->toBe(['wf_1'])
        ->and($context->maxSteps)->toBe(50)
        ->and($context->status)->toBe('running')
        ->and($context->isTerminal())->toBeFalse();
});

it('stores step results losslessly and folds attempts into records', function (): void {
    $context = ExecutionContext::start('exec_1', 'def', 'wf')
        ->withStepResult('a', ['statusCode' => 200, 'outputs' => ['x' => 1]])
        ->withStepAttempt('a')
        ->withStepAttempt('a');

    expect($context->stepResults['a']['statusCode'])->toBe(200)
        ->and($context->attemptFor('a'))->toBe(2)
        ->and($context->getStepAttempts('a'))->toBe(2);
});

it('spends budget and restores it for queue resumes', function (): void {
    $context = ExecutionContext::start('exec_1', 'def', 'wf', maxSteps: 10)->spendStep()->spendStep();

    expect($context->stepsSpent)->toBe(2)
        ->and($context->getBudget())->toBeInstanceOf(Budget::class)
        ->and($context->getBudget()->remainingSteps())->toBe(8);

    $restored = $context->restoreBudget(7, ['parent', 'wf']);
    expect($restored->stepsSpent)->toBe(7)
        ->and($restored->getWorkflowCallStack())->toBe(['parent', 'wf'])
        ->and($restored->getBudget()->remainingSteps())->toBe(3);
});

it('pushes and pops workflow frames on enter and leave', function (): void {
    $context = ExecutionContext::start('exec_1', 'def', 'parent')->enterWorkflow('child');

    expect($context->workflowId)->toBe('child')
        ->and($context->getWorkflowCallStack())->toBe(['parent', 'child']);

    $back = $context->leaveWorkflow();
    expect($back->workflowId)->toBe('parent')
        ->and($back->getWorkflowCallStack())->toBe(['parent']);
});

it('marks terminal statuses through end and suspend semantics', function (): void {
    $suspended = ExecutionContext::start('exec_1', 'def', 'wf')->withStatus('suspended');
    $done = ExecutionContext::start('exec_1', 'def', 'wf')->withStatus('succeeded');

    expect($suspended->status)->toBe('suspended')->and($suspended->isTerminal())->toBeTrue()
        ->and($done->status)->toBe('succeeded')->and($done->isTerminal())->toBeTrue();
});

it('round-trips through WorkflowContext preserving steps, inputs, and budget', function (): void {
    $original = new WorkflowContext('def', ['in' => 1], ['s1' => ['statusCode' => 200]], [], 'wf', 'exec_9');

    $fromContext = ExecutionContext::fromWorkflowContext($original);
    expect($fromContext->inputs['in'])->toBe(1)
        ->and($fromContext->stepResults['s1']['statusCode'])->toBe(200);

    $roundTripped = $fromContext->toWorkflowContext();
    expect($roundTripped->getExecutionId())->toBe('exec_9')
        ->and($roundTripped->getSteps()['s1']['statusCode'])->toBe(200);
});

it('converts to canonical ExecutionState folding record attempts back out', function (): void {
    $context = ExecutionContext::start('exec_r', 'def', 'wf', maxSteps: 77)
        ->withStepResult('a', ['statusCode' => 200])
        ->withStepAttempt('a')
        ->withStepAttempt('a')
        ->withError(new ErrorEntry(type: 'retry_exhausted', stepId: 'a', attempts: 2))
        ->withStatus('failed');

    $state = $context->toExecutionState();

    expect($state)->toBeInstanceOf(ExecutionState::class)
        ->and($state->attemptFor('a'))->toBe(2)
        ->and($state->maxSteps)->toBe(77)
        ->and($state->errors[0]['type'])->toBe('retry_exhausted')
        ->and($state->status)->toBe('failed');
});

it('serializes StepResult to the persistence record shape', function (): void {
    $result = StepResult::success(201, ['id' => 'x'], ['k' => 'v'], responseHeaders: ['Location' => '/x'], attempts: 1);
    $record = $result->toArray();

    expect($record['statusCode'])->toBe(201)
        ->and($record['response']['headers']['Location'])->toBe('/x')
        ->and($record['status'])->toBe('succeeded');
});
