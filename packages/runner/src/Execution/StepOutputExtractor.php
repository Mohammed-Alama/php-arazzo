<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\Data\ExecutionEvaluationInput;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class StepOutputExtractor implements OutputExtractorInterface
{
    public function __construct(
        private OpenApiOperationResolver|DocumentInterface $operationResolver,
        private ExpressionEngineInterface $engine,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContextInterface $context, ?ArazzoDocument $document = null): array
    {
        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        $outputs = [];
        foreach ($step->outputs as $outputName => $expression) {
            if ($expression instanceof Selector) {
                $outputs[$outputName] = $this->engine->evaluateSelector($expression, $context, $step->stepId);

                continue;
            }

            if ($expression instanceof Expression) {
                $raw = trim($expression->raw);

                if (str_starts_with($raw, '$.')) {
                    $outputs[$outputName] = $this->engine->jsonPath($raw, is_array($responseBody) ? $responseBody : []);

                    continue;
                }

                $value = $this->engine->evaluate($expression, new ExecutionEvaluationInput($context, $step->stepId, $document));
                $outputs[$outputName] = $this->castOutputAgainstResponseSchema($step, $context, $document, $expression, $value);
            } else {
                $outputs[$outputName] = $expression;
            }
        }

        return $outputs;
    }

    private function castOutputAgainstResponseSchema(
        Step $step,
        WorkflowContextInterface $context,
        ?ArazzoDocument $document,
        Expression $expression,
        mixed $value,
    ): mixed {
        if ($document === null) {
            return $value;
        }

        $ref = $this->engine->expressionReferences($expression->raw);
        if ($ref === null
            || $ref->kind !== ReferenceKind::Step
            || $ref->part !== 'response'
            || $ref->httpPart !== 'body'
            || $ref->jsonPointer === null
        ) {
            return $value;
        }

        try {
            $resolved = $this->resolveOperation($step, $document);
            $operation = $resolved->cebeOperation;
        } catch (\RuntimeException) {
            return $value;
        }

        $statusCode = (string) ($context->getSteps()[$step->stepId]['response']['statusCode'] ?? '');
        $responses = $operation->responses;
        if (!$responses instanceof Responses) {
            return $value;
        }
        $response = $responses->getResponse($statusCode) ?? $responses->getResponse('default');
        if (!$response instanceof Response) {
            return $value;
        }

        $schema = $response->content['application/json']->schema ?? null;
        if ($schema instanceof Reference) {
            $schema = $schema->resolve();
        }
        $leafSchema = $this->resolveSchemaAtPointer($schema instanceof Schema ? $schema : null, $ref->jsonPointer);

        return $this->castToSchemaType($value, $leafSchema);
    }

    private function resolveOperation(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        if ($this->operationResolver instanceof DocumentInterface) {
            return $this->operationResolver->resolveOperation($step, $document);
        }

        return $this->operationResolver->resolve($step, $document);
    }

    private function resolveSchemaAtPointer(?Schema $schema, string $pointer): ?Schema
    {
        if ($schema === null) {
            return null;
        }

        $segments = array_filter(explode('/', ltrim($pointer, '/')), static fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if ($schema->type === 'array' && $schema->items instanceof Schema) {
                $schema = $schema->items;

                continue;
            }

            if (isset($schema->properties[$segment]) && $schema->properties[$segment] instanceof Schema) {
                $schema = $schema->properties[$segment];

                continue;
            }

            return null;
        }

        return $schema;
    }

    private function castToSchemaType(mixed $value, ?Schema $schema): mixed
    {
        if ($schema === null || $schema->type === null) {
            return $value;
        }

        try {
            return match ($schema->type) {
                'integer' => TypeCaster::asInteger($value),
                'number' => TypeCaster::asFloat($value),
                'string' => TypeCaster::asString($value),
                'boolean' => TypeCaster::asBoolean($value),
                'array' => TypeCaster::asArray($value),
                default => $value,
            };
        } catch (InvalidArgumentException $e) {
            $this->logger?->warning("Failed to cast value to schema type '{$schema->type}': {$e->getMessage()}");

            return $value;
        }
    }
}
