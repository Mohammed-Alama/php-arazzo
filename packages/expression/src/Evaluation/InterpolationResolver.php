<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Evaluation;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Expression\Evaluation\Data\EvaluationContext;
use Alama\Arazzo\Expression\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use BadMethodCallException;

/**
 * Internal adapter: an {@see ExpressionResolverInterface} that only evaluates
 * expressions (used to drive string interpolation). Every other resolver
 * capability is unsupported.
 *
 * @internal stays out of the advertised contract; used by the expression facade.
 */
final class InterpolationResolver implements ExpressionResolverInterface
{
    public function __construct(private readonly ExpressionEvaluatorInterface $evaluator) {}

    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, new EvaluationContext($context, $currentStepId));
    }

    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        throw new BadMethodCallException('Interpolation does not extract step outputs.');
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        throw new BadMethodCallException('Interpolation does not evaluate success criteria.');
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        throw new BadMethodCallException('Interpolation does not evaluate criteria.');
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        throw new BadMethodCallException('Interpolation does not validate response schemas.');
    }
}
