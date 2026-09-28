<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Runner\Execution\Data\Transition;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Execution\Exceptions\StepBudgetExceededException;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runtime\Policy\RetryPolicy;
use Alama\Arazzo\Runtime\State\Data\ExecutionContext;
use Alama\Arazzo\Runtime\State\Data\StepResult;

function workflowEngineResolver(): EvaluationEngineInterface
{
    return new class() implements EvaluationEngineInterface
    {
        public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
        {
            return $expression->raw;
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
        {
            return null;
        }

        public function queryXPath(mixed $rootValue, string $selector, string $version): mixed
        {
            return null;
        }

        public function supportedXPathVersions(): array
        {
            return [];
        }

        public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
        {
            return $value;
        }

        public function replacePayload(Step $step, array $body, ?callable $resolveValue = null, ?WorkflowContext $context = null): array
        {
            return $body;
        }

        public function jsonPath(string $expression, array|object $data): mixed
        {
            return null;
        }

        public function jsonPointer(array $data, ?string $pointer): mixed
        {
            return null;
        }
    };
}

function workflowEngineRetryPolicy(): RetryPolicy
{
    return new RetryPolicy();
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
    return new Step($id, null, new StepTarget(), new StepFlow(dependsOn: $dependsOn), new StepIo());
}

function workflowEngineState(string $executionId = 'exec_1', string $definitionId = 'definition_1', string $workflowId = 'workflow_1', int $maxSteps = 1000): ExecutionContext
{
    return ExecutionContext::start($executionId, $definitionId, $workflowId, maxSteps: $maxSteps);
}

function workflowEngineStepResult(array $outputs = []): StepResult
{
    return StepResult::success(200, $outputs, [], attempts: 1);
}

it('creates every transition kind with its explicit state', function (): void {
    $state = ExecutionContext::start('exec_1', 'definition_1', 'workflow_1');

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
    $state = workflowEngineState()->withStepResult('first', workflowEngineStepResult());

    $transition = (new WorkflowEngine(workflowEngineResolver(), workflowEngineRetryPolicy()))->transition(workflowEngineDocument($workflow), $workflow, $first, $state, true);

    expect($transition->type)->toBe(TransitionType::Next)->and($transition->stepId)->toBe('second');
});

it('enforces the shared step budget before an attempt', function (): void {
    $step = workflowEngineStep('first');
    $workflow = workflowEngineWorkflow([$step]);
    $state = workflowEngineState(maxSteps: 1)->spendStep();

    (new WorkflowEngine(workflowEngineResolver(), workflowEngineRetryPolicy()))->transition(workflowEngineDocument($workflow), $workflow, $step, $state, true);
})->throws(StepBudgetExceededException::class);
