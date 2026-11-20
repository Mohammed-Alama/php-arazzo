<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
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
use Alama\Arazzo\Evaluation\Data\CriterionResult;
use Alama\Arazzo\Evaluation\Data\CriterionResultCollection;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runtime\Policy\RetryPolicy;
use Alama\Arazzo\Runtime\State\Data\ExecutionContext;

function skipEngineResolver(): EvaluationEngineInterface
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

        /** @return list<CriterionResult> */
        public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): CriterionResultCollection
        {
            return new CriterionResultCollection([]);
        }

        public function evaluateSelector(Selector $selector, WorkflowContextInterface $context, string $stepId): mixed
        {
            return null;
        }

        public function interpolate(string $value, WorkflowContextInterface $context, string $stepId): string
        {
            return $value;
        }

        public function resolveValue(mixed $value, WorkflowContextInterface $context, ?string $stepId = null): mixed
        {
            if (is_string($value)) {
                return $value;
            }
            if ($value instanceof Expression) {
                return $this->evaluate($value, new EvaluationContext($context, $stepId));
            }
            if ($value instanceof Selector) {
                return $this->evaluateSelector($value, $context, $stepId);
            }

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

function skipEngineWorkflow(array $steps): Workflow
{
    return new Workflow('workflow_1', null, null, null, [], $steps, [], [], [], []);
}

function skipEngineDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

function skipEngineStep(string $id, array $dependsOn = []): Step
{
    return new Step($id, null, new StepTarget(), new StepFlow(dependsOn: $dependsOn), new StepIo());
}

function skipEngineState(string $executionId = 'exec_1', string $definitionId = 'definition_1', string $workflowId = 'workflow_1', int $maxSteps = 1000): ExecutionContext
{
    return ExecutionContext::start($executionId, $definitionId, $workflowId, maxSteps: $maxSteps);
}

function skipEngineStepResult(array $outputs = []): array
{
    return ['outputs' => $outputs, 'statusCode' => 200, 'attempts' => 1];
}

describe('WorkflowEngine goto skip semantics', function () {
    it('goto transition sets skippedSteps to steps between source and target', function (): void {
        $create = skipEngineStep('create');
        $list = skipEngineStep('list');
        $fetch = skipEngineStep('fetch');
        $workflow = skipEngineWorkflow([$create, $list, $fetch]);
        $goto = new SuccessGotoAction('goto', 'fetch', null, []);
        $step = new Step('create', null, new StepTarget(), new StepFlow(onSuccess: [$goto]), new StepIo());
        $state = skipEngineState()->withStepResult('create', skipEngineStepResult());

        $transition = (new WorkflowEngine(skipEngineResolver(), new RetryPolicy()))->transition(
            skipEngineDocument($workflow), $workflow, $step, $state, true,
        );

        expect($transition->type)->toBe(TransitionType::Goto)
            ->and($transition->stepId)->toBe('fetch')
            ->and($transition->state->getSkippedSteps())->toBe(['list']);
    });

    it('goto does not skip when target is before source (backward jump)', function (): void {
        $fetch = skipEngineStep('fetch');
        $list = skipEngineStep('list');
        $create = skipEngineStep('create');
        $workflow = skipEngineWorkflow([$fetch, $list, $create]);
        $goto = new SuccessGotoAction('goto', 'fetch', null, []);
        $step = new Step('create', null, new StepTarget(), new StepFlow(onSuccess: [$goto]), new StepIo());
        $state = skipEngineState()->withStepResult('create', skipEngineStepResult());

        $transition = (new WorkflowEngine(skipEngineResolver(), new RetryPolicy()))->transition(
            skipEngineDocument($workflow), $workflow, $step, $state, true,
        );

        expect($transition->state->getSkippedSteps())->toBe([]);
    });

    it('goto does not skip when source and target are the same step', function (): void {
        $create = skipEngineStep('create');
        $workflow = skipEngineWorkflow([$create]);
        $goto = new SuccessGotoAction('goto', 'create', null, []);
        $step = new Step('create', null, new StepTarget(), new StepFlow(onSuccess: [$goto]), new StepIo());
        $state = skipEngineState()->withStepResult('create', skipEngineStepResult());

        $transition = (new WorkflowEngine(skipEngineResolver(), new RetryPolicy()))->transition(
            skipEngineDocument($workflow), $workflow, $step, $state, true,
        );

        expect($transition->state->getSkippedSteps())->toBe([]);
    });

    it('nextRunnableStep skips steps in the goto skip set', function (): void {
        $create = skipEngineStep('create');
        $list = skipEngineStep('list');
        $fetch = skipEngineStep('fetch');
        $workflow = skipEngineWorkflow([$create, $list, $fetch]);
        $goto = new SuccessGotoAction('goto', 'fetch', null, []);
        $step = new Step('create', null, new StepTarget(), new StepFlow(onSuccess: [$goto]), new StepIo());
        $state = skipEngineState()
            ->withStepResult('create', skipEngineStepResult())
            ->withStepResult('fetch', skipEngineStepResult())
            ->withSkippedSteps(['list']);

        $engine = new WorkflowEngine(skipEngineResolver(), new RetryPolicy());
        $transition = $engine->transition(
            skipEngineDocument($workflow), $workflow, $step, $state, true,
        );

        $next = $engine->nextRunnableStep($workflow, $transition->state, 'fetch');

        expect($next)->toBeNull();
    });

    it('nextRunnableStep returns non-skipped runnable step', function (): void {
        $create = skipEngineStep('create');
        $list = skipEngineStep('list');
        $fetch = skipEngineStep('fetch');
        $other = skipEngineStep('other');
        $workflow = skipEngineWorkflow([$create, $list, $fetch, $other]);
        $goto = new SuccessGotoAction('goto', 'fetch', null, []);
        $step = new Step('create', null, new StepTarget(), new StepFlow(onSuccess: [$goto]), new StepIo());
        $state = skipEngineState()
            ->withStepResult('create', skipEngineStepResult())
            ->withStepResult('fetch', skipEngineStepResult())
            ->withSkippedSteps(['list']);

        $engine = new WorkflowEngine(skipEngineResolver(), new RetryPolicy());
        $transition = $engine->transition(
            skipEngineDocument($workflow), $workflow, $step, $state, true,
        );

        $next = $engine->nextRunnableStep($workflow, $transition->state, 'fetch');

        expect($next)->toBe('other');
    });
});
