<?php

declare(strict_types=1);

use Alama\Arazzo\Context\ExecutionState;
use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Exceptions\StepBudgetExceededException;
use Alama\Arazzo\Execution\TransitionType;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Tests\Support\Fx;
use Alama\Arazzo\Tests\Support\TestExpressionResolver;

it('persists and restores the shared budget across queue job boundaries', function (): void {
    $state = ExecutionState::start('exec_b', 'def', 'wf', [], maxSteps: 3);
    $state = $state->spendStep()->spendStep();

    $context = new WorkflowContext(
        definitionId: 'def',
        inputs: [],
        workflowId: 'wf',
        executionId: 'exec_b',
        stepsSpent: $state->stepsSpent,
        workflowCallStack: $state->workflowCallStack,
    );

    // Simulate a worker serialize() -> persist -> reconcile() round trip.
    $persisted = [
        'definitionId' => $context->getDefinitionId(),
        'workflowId' => $context->getWorkflowId(),
        'steps' => [],
        'inputs' => [],
        'components' => [],
        'stepsSpent' => $context->getStepsSpent(),
        'workflowCallStack' => $context->getWorkflowCallStack(),
    ];

    $restored = (new ReflectionClass(WorkflowContext::class))
        ->getMethod('withBudget')
        ->invoke(
            new WorkflowContext('def', [], workflowId: 'wf', executionId: 'exec_b'),
            (int) ($persisted['stepsSpent'] ?? 0),
            $persisted['workflowCallStack'],
        );

    expect($restored->getStepsSpent())->toBe(2)
        ->and($restored->getWorkflowCallStack())->toBe(['wf']);

    // The rebuilt engine state must keep consuming from the same pool:
    // only one unit remains before the budget of 3 is exceeded.
    $rebuilt = ExecutionState::start('exec_b', 'def', 'wf', [], maxSteps: 3, stepsSpent: $restored->getStepsSpent(), workflowCallStack: $restored->getWorkflowCallStack());

    expect($rebuilt->stepsSpent)->toBe(2);

});

it('shares one step budget between parent and nested sub-workflow invocation', function (): void {
    $resolver = new TestExpressionResolver();

    $childWf = Fx::wf('child', [Fx::step('c1'), Fx::step('c2')]);
    $parentStep = Fx::step('hop', null, null, null, onSuccess: [
        new SubWorkflowSuccessAction('invoke-child', 'child', [], []),
    ]);
    $parentWf = Fx::wf('parent', [$parentStep, Fx::step('after')]);
    $document = Fx::doc([$parentWf]);

    $engine = new WorkflowEngine($resolver);
    $state = ExecutionState::start('exec_i', 'def', 'parent', [], maxSteps: 5);

    // Parent spends one unit before invoking.
    $state = $state->spendStep()->withStepResult('hop', ['status' => 'success']);
    $transition = $engine->transition($document, $parentWf, $parentStep, $state, true);

    expect($transition->type)->toBe(TransitionType::Invoke)
        ->and($transition->state->workflowCallStack)->toContain('child');

    // The engine guards re-entry through the shared call stack...
    expect($transition->state->workflowCallStack)->toBe(['parent', 'child']);

    // ...and a fresh child context inherits the parent's consumption.
    $childContext = WorkflowContext::forChildInvocation(
        new WorkflowContext('def', [], workflowId: 'parent', executionId: 'p1', stepsSpent: 1, workflowCallStack: ['parent']),
        $childWf,
        [],
    );

    expect($childContext->getStepsSpent())->toBe(1)
        ->and($childContext->getWorkflowCallStack())->toBe(['parent', 'child']);

    // Two child attempts push the SHARED pool to its limit of 5.
    $childState = ExecutionState::start(
        (string) $childContext->getExecutionId(),
        'def',
        'child',
        [],
        maxSteps: 5,
        workflowCallStack: $childContext->getWorkflowCallStack(),
        stepsSpent: $childContext->getStepsSpent(),
    );
    $childState = $childState->spendStep()->spendStep(); // c1, c2
    $childState = $childState->spendStep(); // parent's "after"

    expect($childState->stepsSpent)->toBe(4);

    $this->expectException(StepBudgetExceededException::class);
    $engine->transition($document, $childWf, Fx::step('overflow'), $childState->spendStep(), true);
});
