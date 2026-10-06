<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Sync;

use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepExecutionOutcome;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\ReusableParameterResolver;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;

/**
 * Executes steps that target a nested workflow via workflowId. The child
 * workflow runs in its own child context; its outputs are surfaced as the
 * step's outputs so parent expressions like {$steps.s.outputs.x} resolve.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class SubWorkflowStepExecutor implements OperationExecutorPluginInterface, StepProtocolExecutorInterface
{
    /**
     * Registry ordering (ascending, first supports() wins). Unlike the HTTP
     * pair, this is NOT a disjoint claim: a workflowId target with a null
     * action also satisfies HttpStepExecutor::supports(), so the runner-native
     * plugin must sort before HttpStepExecutor::PLUGIN_PRIORITY (100).
     */
    public const PLUGIN_PRIORITY = 0;

    public function __construct(
        private WorkflowExecutor $executor,
        private EvaluationEngineInterface $evaluationEngine,
    ) {}

    public function name(): string
    {
        return 'subworkflow';
    }

    public function priority(): int
    {
        return self::PLUGIN_PRIORITY;
    }

    public function supports(Step $step, ArazzoDocument $document): bool
    {
        return $step->target->workflowId !== null && !in_array($step->target->action, ['send', 'receive'], true);
    }

    public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document, string $executionId): StepExecutionOutcome
    {
        $target = null;
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $step->target->workflowId) {
                $target = $workflow;
                break;
            }
        }

        if ($target === null) {
            throw ExecutionException::subWorkflowNotFound((string) $step->target->workflowId);
        }

        $evaluationContext = new EvaluationContext($context, $step->stepId, $document);

        $bound = [];
        $parameters = (new ReusableParameterResolver())->resolve($step->io->parameters, $document);

        foreach ($parameters as $parameter) {
            $bound[$parameter->name] = $parameter->value instanceof Expression
                ? $this->evaluationEngine->evaluate($parameter->value, $evaluationContext)
                : $parameter->value;
        }

        $child = WorkflowContext::forChildInvocation($context, $target, $bound);
        $result = $this->executor->execute($target, $document, $bound, $child);

        // A completed sub-workflow has no HTTP semantics; 200 marks success.
        return StepExecutionOutcome::resolved(200, $result->outputs, [], $bound, [
            'method' => 'WORKFLOW',
            'url' => '#workflows/'.$target->workflowId,
        ]);
    }
}
