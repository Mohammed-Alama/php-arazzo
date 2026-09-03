<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;

final class TestExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed
    {
        return true;
    }

    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool
    {
        return $criteria === [];
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}
}
