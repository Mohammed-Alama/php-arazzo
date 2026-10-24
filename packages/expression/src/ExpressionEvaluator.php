<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Ast\SelfRef;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\Ast\WorkflowRef;
use Alama\Arazzo\Expression\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\Expression\Internal\StepReferenceResolver;
use Alama\Arazzo\Expression\Internal\ValueReader;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

/**
 * Evaluates a parsed runtime expression against the current workflow state.
 *
 * Step, output, message and HTTP-meta references live in StepReferenceResolver;
 * this class keeps the workflow-level references. Both stayed under PHPMD's
 * class thresholds after the split (#260).
 *
 * @internal stays out of the advertised contract; consumed by the ExpressionEngine facade.
 */
final class ExpressionEvaluator
{
    private readonly ValueReader $values;

    private readonly StepReferenceResolver $steps;

    public function __construct(
        JsonPointer $jsonPointer,
    ) {
        $this->values = new ValueReader($jsonPointer);
        $this->steps = new StepReferenceResolver($this->values);
    }

    public function evaluate(Expression $expression, EvaluationInputInterface $context): mixed
    {
        $ast = new ExpressionParser()->parse($expression->raw);

        return $this->evaluateAst($ast, $context);
    }

    private function evaluateAst(ExpressionAst $ast, EvaluationInputInterface $context): mixed
    {
        if ($this->steps->supports($ast)) {
            return $this->steps->resolve($ast, $context);
        }

        return match (true) {
            $ast instanceof InputRef => $this->values->withPointer(
                $context->getWorkflowContext()->getInputs()[$ast->name] ?? null,
                $ast->jsonPointer,
            ),
            $ast instanceof WorkflowRef => $this->workflow($ast, $context),
            $ast instanceof SelfRef => $context->getDocument()?->self,
            $ast instanceof ComponentRef => $this->component($ast, $context),
            $ast instanceof SourceRef => $this->source($ast, $context),
            default => null,
        };
    }

    private function workflow(WorkflowRef $ast, EvaluationInputInterface $context): mixed
    {
        $workflowData = $context->getWorkflowContext()->getWorkflows()[$ast->workflowId] ?? null;
        if ($workflowData === null) {
            return null;
        }

        return $workflowData[$ast->partKind][$ast->name] ?? null;
    }

    private function component(ComponentRef $ast, EvaluationInputInterface $context): mixed
    {
        if ($ast->type !== 'parameters') {
            return null;
        }

        $components = $context->getWorkflowContext()->getComponents();

        return $this->values->map($components, 'parameters')[$ast->name] ?? null;
    }

    private function source(SourceRef $ast, EvaluationInputInterface $context): mixed
    {
        $document = $context->getDocument();
        if ($document === null) {
            return null;
        }

        foreach ($document->sourceDescriptions as $sourceDesc) {
            if ($sourceDesc->name !== $ast->name) {
                continue;
            }

            return match ($ast->subPath) {
                'url' => $sourceDesc->url,
                'type' => $sourceDesc->type->value,
                default => null,
            };
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function jsonPointer(array $data, ?string $pointer): mixed
    {
        return $this->values->pointer($data, $pointer);
    }
}
