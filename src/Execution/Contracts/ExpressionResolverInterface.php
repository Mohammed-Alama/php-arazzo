<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Psr\Http\Message\RequestInterface;

interface ExpressionResolverInterface
{
    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface;

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array;

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;

    /**
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool;

    /**
     * Validates a decoded response body against the step's OpenAPI schema.
     * Throws SchemaValidationException if validation fails.
     * Does nothing if no matching schema is found.
     *
     * @throws SchemaValidationException
     */
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void;
}
