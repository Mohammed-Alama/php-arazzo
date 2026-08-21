<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Runner\Context\ExecutionState;

it('preserves every execution field through serialization', function (): void {
    $state = new ExecutionState(
        executionId: 'exec_1',
        definitionId: 'definition_1',
        workflowId: 'workflow_1',
        currentStepId: null,
        inputs: ['nullable' => null, 'false' => false, 'zero' => 0, 'empty' => []],
        stepAttempts: ['step_1' => 2],
        stepResults: ['step_1' => ['outputs' => ['answer' => 42]]],
        dependencies: ['step_2' => ['step_1']],
        outputs: ['done' => false],
        errors: [['message' => 'failed']],
        stepsSpent: 3,
        maxSteps: 10,
        workflowCallStack: ['workflow_1'],
        maxWorkflowDepth: 4,
        components: ['token' => null],
    );

    $restored = ExecutionState::fromArray($state->toArray());

    expect($restored)->toEqual($state)
        ->and($restored->inputs)->toBe(['nullable' => null, 'false' => false, 'zero' => 0, 'empty' => []])
        ->and($restored->errors)->toBe([['message' => 'failed']]);
});

it('returns immutable updates for attempts, budget, outputs, and workflow stack', function (): void {
    $state = ExecutionState::start('exec_1', 'definition_1', 'workflow_1', maxSteps: 2)
        ->withStepAttempt('step_1')
        ->spendStep()
        ->withOutput('accepted', true)
        ->enterWorkflow('workflow_2');

    expect($state->attemptFor('step_1'))->toBe(1)
        ->and($state->stepsSpent)->toBe(1)
        ->and($state->outputs)->toBe(['accepted' => true])
        ->and($state->workflowCallStack)->toBe(['workflow_1', 'workflow_2']);
});
