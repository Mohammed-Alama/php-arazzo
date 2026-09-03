<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

interface ExpressionResolverInterface
{
    public function evaluate(Expression $expression, WorkflowContextInterface $context, ?string $currentStepId = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array;

    public function evaluateSuccessCriteria(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param  list<SuccessCriterion>  $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): bool;

    /**
     * Validates a decoded response body against the step's OpenAPI schema.
     * Throws an exception if validation fails.
     * Does nothing if no matching schema is found.
     */
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void;
}
