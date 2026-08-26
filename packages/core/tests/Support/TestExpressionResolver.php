<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;

final class TestExpressionResolver implements ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return true;
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
        return $criteria === [];
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}
}
