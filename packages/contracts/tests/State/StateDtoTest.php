<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;

it('declares the shared state DTOs')
    ->expect(class_exists(WorkflowContext::class))
    ->toBeTrue()
    ->and(class_exists(ExecutionState::class))
    ->toBeTrue();

it('round-trips every execution field through toArray and fromArray', function (): void {
    $state = new ExecutionState(
        executionId: 'exec_1',
        definitionId: 'definition_1',
        workflowId: 'workflow_1',
        currentStepId: 'step_1',
        inputs: ['nullable' => null, 'false' => false, 'empty' => []],
        stepAttempts: ['step_1' => 2],
        stepResults: ['step_1' => ['outputs' => ['answer' => 42]]],
        dependencies: ['step_2' => ['step_1']],
        outputs: ['done' => false],
        errors: [['message' => 'failed']],
        stepsSpent: 3,
        maxSteps: 10,
        workflowCallStack: ['workflow_1', 'workflow_2'],
        maxWorkflowDepth: 4,
        components: ['token' => null],
        status: 'completed',
    );

    $restored = ExecutionState::fromArray($state->toArray());

    expect($restored->toArray())->toBe($state->toArray())
        ->and($restored)->toEqual($state);
});

it('keeps the persisted key set and its order stable', function (): void {
    $state = ExecutionState::start('exec_1', 'definition_1', 'workflow_1');

    expect(array_keys($state->toArray()))->toBe([
        'executionId', 'definitionId', 'workflowId', 'currentStepId', 'inputs',
        'stepAttempts', 'stepResults', 'dependencies', 'outputs', 'errors',
        'stepsSpent', 'maxSteps', 'workflowCallStack', 'maxWorkflowDepth',
        'components', 'status', 'workflows',
    ]);
});

it('owns workflow outputs across canonical state, context, and persistence conversions', function (): void {
    $state = ExecutionState::start('exec_w', 'def', 'wf', ['seed' => false])
        ->withWorkflowData('base', ['outputs' => ['x' => 1, 'nil' => null]])
        ->withWorkflowData('base', ['inputs' => ['q' => 'v']])
        ->spendStep();

    $workflows = [
        'base' => [
            'inputs' => ['q' => 'v'],
            'outputs' => ['x' => 1, 'nil' => null],
        ],
    ];

    $context = $state->toContext();
    expect($context->getWorkflows())->toBe($workflows)
        ->and($context->getStepsSpent())->toBe(1);

    $fromContext = ExecutionState::fromContext($context, maxSteps: 9, maxWorkflowDepth: 3);
    expect($fromContext->toContext()->getWorkflows())->toBe($workflows)
        ->and($fromContext->stepsSpent)->toBe(1)
        ->and($fromContext->maxSteps)->toBe(9);

    $persisted = $state->toArray();
    expect($persisted['workflows'])->toBe($workflows)
        ->and(ExecutionState::fromArray($persisted)->toContext()->getWorkflows())->toBe($workflows);
});

it('round-trips workflow data through the context persistence payload', function (): void {
    $workflows = ['base' => ['inputs' => ['q' => 'v'], 'outputs' => ['x' => null, 'zero' => 0]]];
    $context = (new WorkflowContext('def', ['in' => 1], [], [], 'wf', 'exec_p'))
        ->withWorkflowData('base', $workflows['base'])
        ->withBudget(5, ['wf', 'child']);

    $persisted = $context->toArray();
    expect($persisted['workflows'])->toBe($workflows);

    $restored = WorkflowContext::fromPersisted($persisted, 'exec_p');
    expect($restored->getWorkflows())->toBe($workflows)
        ->and($restored->getStepsSpent())->toBe(5)
        ->and($restored->getWorkflowCallStack())->toBe(['wf', 'child']);

    $incoming = (new WorkflowContext('def', ['in' => 2], [], [], 'wf', 'exec_new'))
        ->withWorkflowData('base', ['outputs' => ['stale' => true]])
        ->withWorkflowData('other', ['outputs' => ['kept' => 1]]);

    expect(WorkflowContext::reconciled($incoming, $persisted, 'exec_p')->getWorkflows())->toBe([
        'base' => $workflows['base'],
        'other' => ['inputs' => [], 'outputs' => ['kept' => 1]],
    ]);
});

it('falls back to defaults instead of throwing on a corrupt persisted payload', function (): void {
    $state = ExecutionState::fromArray([
        'executionId' => 42,
        'definitionId' => null,
        'workflowId' => ['not', 'a', 'string'],
        'currentStepId' => 7,
        'inputs' => 'not-an-array',
        'stepAttempts' => null,
        'stepResults' => false,
        'dependencies' => 'nope',
        'outputs' => null,
        'errors' => 'nope',
        'stepsSpent' => '3',
        'maxSteps' => 4.5,
        'workflowCallStack' => 'workflow_1',
        'maxWorkflowDepth' => null,
        'components' => 'nope',
        'status' => 99,
        'workflows' => 'nope',
    ]);

    expect($state->executionId)->toBe('')
        ->and($state->toContext()->getWorkflows())->toBe([])
        ->and($state->definitionId)->toBe('')
        ->and($state->workflowId)->toBe('')
        ->and($state->currentStepId)->toBeNull()
        ->and($state->inputs)->toBe([])
        ->and($state->stepAttempts)->toBe([])
        ->and($state->stepResults)->toBe([])
        ->and($state->dependencies)->toBe([])
        ->and($state->outputs)->toBe([])
        ->and($state->errors)->toBe([])
        ->and($state->stepsSpent)->toBe(0)
        ->and($state->maxSteps)->toBe(1000)
        ->and($state->workflowCallStack)->toBe([''])
        ->and($state->maxWorkflowDepth)->toBe(32)
        ->and($state->components)->toBe([])
        ->and($state->status)->toBe('running');
});

it('survives corrupt step records in the workflow context', function (): void {
    $context = new WorkflowContext('definition_1', steps: ['step_1' => 'corrupt']);

    expect($context->getStepAttempts('step_1'))->toBe(0)
        ->and($context->getStepStatus('step_1'))->toBeNull()
        ->and($context->getStepAttempts('missing'))->toBe(0)
        ->and($context->getStepStatus('missing'))->toBeNull();

    $updated = $context
        ->withStepOutput('step_1', 'answer', 42)
        ->withStepStatus('step_1', StepStatus::Succeeded)
        ->withStepAttemptIncremented('step_1')
        ->withStepRequest('step_1', ['method' => 'GET'])
        ->withStepResponse('step_1', ['status' => 200]);

    expect($updated->getSteps()['step_1'])->toBe([
        'outputs' => ['answer' => 42],
        'status' => StepStatus::Succeeded,
        'attempts' => 1,
        'request' => ['method' => 'GET'],
        'response' => ['status' => 200],
    ])
        ->and($updated->getStepAttempts('step_1'))->toBe(1)
        ->and($updated->getStepStatus('step_1'))->toBe(StepStatus::Succeeded);
});
