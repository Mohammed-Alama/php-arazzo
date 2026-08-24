<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Context\WorkflowContext;

/**
 * Card A (architecture review): ONE owner of the state<->context mapping.
 * These tests pin the canonical semantics every former hand-rolled
 * converter now inherits.
 */
it('folds attempt counters into step records when mapping to context', function (): void {
    $state = ExecutionState::start('e1', 'def', 'wf')
        ->withStepResult('a', ['status' => 'success'])
        ->withStepResult('b', ['status' => 'failure'])
        ->withStepAttempt('a')
        ->withStepAttempt('a');

    $context = $state->toContext();

    expect($context->getSteps()['a']['attempts'])->toBe(2)
        ->and($context->getSteps()['b']['attempts'] ?? null)->toBeNull('steps without attempts carry no counter')
        ->and($context->getStepsSpent())->toBe(0);
});

it('carries budget and call stack through both directions', function (): void {
    $state = ExecutionState::start(
        'e1', 'def', 'parent',
        maxSteps: 10, maxWorkflowDepth: 4,
        stepsSpent: 3,
        workflowCallStack: ['parent', 'child'],
    );

    $context = $state->toContext();

    expect($context->getStepsSpent())->toBe(3)
        ->and($context->getWorkflowCallStack())->toBe(['parent', 'child']);

    // Limits are adapter policy (not run-state), so they stay explicit...
    $roundTrip = ExecutionState::fromContext($context, maxSteps: 10, maxWorkflowDepth: 4);

    expect($roundTrip->stepsSpent)->toBe(3)
        ->and($roundTrip->workflowCallStack)->toBe(['parent', 'child'])
        ->and($roundTrip->maxSteps)->toBe(10)
        ->and($roundTrip->maxWorkflowDepth)->toBe(4);
});

it('replays record attempts back into the attempt map from context', function (): void {
    $context = new WorkflowContext('def', [], workflowId: 'wf', executionId: 'e2');
    $context = $context
        ->withStepResult('a', ['status' => 'success', 'attempts' => 3])
        ->withStepResult('b', ['status' => 'success']);

    $state = ExecutionState::fromContext($context);

    expect($state->attemptFor('a'))->toBe(3)
        ->and($state->attemptFor('b'))->toBe(0)
        ->and(array_keys($state->stepResults))->toBe(['a', 'b']);
});

it('serializes to the persistence payload including budget fields', function (): void {
    $context = new WorkflowContext(
        definitionId: 'def',
        inputs: ['x' => 1],
        steps: ['a' => ['status' => 'success']],
        workflowId: 'wf',
        executionId: 'e3',
        stepsSpent: 2,
        workflowCallStack: ['wf'],
    );

    $payload = $context->toArray();

    expect($payload)->toHaveKeys(['definitionId', 'workflowId', 'steps', 'inputs', 'components', 'stepsSpent', 'workflowCallStack'])
        ->and($payload['stepsSpent'])->toBe(2);
});

it('reconciles job context with persisted state where persisted wins', function (): void {
    $incoming = new WorkflowContext('def', [], workflowId: 'wf', executionId: 'e4');
    $incoming = $incoming->withStepResult('a', ['status' => 'stale']);

    $persisted = [
        'definitionId' => 'def',
        'workflowId' => 'wf',
        'steps' => ['a' => ['status' => 'fresh'], 'c' => ['status' => 'success']],
        'inputs' => [],
        'components' => [],
        'stepsSpent' => 5,
        'workflowCallStack' => ['wf', 'child'],
    ];

    $reconciled = WorkflowContext::reconciled($incoming, $persisted, 'e4');

    expect($reconciled->getExecutionId())->toBe('e4')
        ->and($reconciled->getSteps()['a']['status'])->toBe('fresh')
        ->and($reconciled->getSteps())->toHaveKey('c')
        ->and($reconciled->getStepsSpent())->toBe(5)
        ->and($reconciled->getWorkflowCallStack())->toBe(['wf', 'child']);
});

it('hydrates from persisted payloads without requiring budget keys', function (): void {
    $context = WorkflowContext::fromPersisted([
        'definitionId' => 'def',
        'workflowId' => 'wf',
        'steps' => ['a' => ['ok' => true]],
        'inputs' => [],
        'components' => [],
    ], 'exec_9');

    expect($context->getDefinitionId())->toBe('def')
        ->and($context->getExecutionId())->toBe('exec_9')
        ->and($context->getStepsSpent())->toBe(0)
        ->and($context->getWorkflowCallStack())->toBe([]);
});
