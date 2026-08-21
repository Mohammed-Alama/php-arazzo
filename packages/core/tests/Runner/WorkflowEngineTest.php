<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Exceptions\StepBudgetExceededException;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Execution\Transition;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Psr\Http\Message\RequestInterface;

function workflowEngineResolver(): ExpressionResolverInterface
{
    return new class() implements ExpressionResolverInterface
    {
        public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
        {
            return $expression->raw;
        }

        public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
        {
        }

        public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
        {
            throw new \LogicException('not used');
        }

        public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
        {
            return [];
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }
    };
}

/** @param list<Step> $steps */
function workflowEngineWorkflow(array $steps): Workflow
{
    return new Workflow('workflow_1', null, null, null, [], $steps, [], [], [], []);
}
function workflowEngineDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}
function workflowEngineStep(string $id, array $dependsOn = []): Step
{
    return new Step($id, null, null, null, null, [], null, [], [], [], [], $dependsOn);
}

it('creates every transition kind with its explicit state', function (): void {
    $state = ExecutionState::start('exec_1', 'definition_1', 'workflow_1');

    expect(Transition::next($state, 'step_2')->type)->toBe(TransitionType::Next)
        ->and(Transition::retry($state, 'step_1', 3)->delaySeconds)->toBe(3)
        ->and(Transition::goto($state, 'step_2', 'workflow_2')->workflowId)->toBe('workflow_2')
        ->and(Transition::end($state, 'succeeded')->status)->toBe('succeeded')
        ->and(Transition::suspend($state)->type)->toBe(TransitionType::Suspend);
});

it('moves to the next dependency-ready step after a successful attempt', function (): void {
    $first = workflowEngineStep('first');
    $second = workflowEngineStep('second', ['first']);
    $workflow = workflowEngineWorkflow([$first, $second]);
    $state = ExecutionState::start('exec_1', 'definition_1', $workflow->workflowId)->withStepResult('first', ['outputs' => []]);

    $transition = (new WorkflowEngine(workflowEngineResolver()))->transition(workflowEngineDocument($workflow), $workflow, $first, $state, true);

    expect($transition->type)->toBe(TransitionType::Next)->and($transition->stepId)->toBe('second');
});

it('enforces the shared step budget before an attempt', function (): void {
    $step = workflowEngineStep('first');
    $workflow = workflowEngineWorkflow([$step]);
    $state = ExecutionState::start('exec_1', 'definition_1', $workflow->workflowId, maxSteps: 1)->spendStep();

    (new WorkflowEngine(workflowEngineResolver()))->transition(workflowEngineDocument($workflow), $workflow, $step, $state, true);
})->throws(StepBudgetExceededException::class);
