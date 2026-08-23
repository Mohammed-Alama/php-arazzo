<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\EvaluationContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Evaluation\JsonPathEvaluator;
use Alama\Arazzo\Runner\Evaluation\SelectorEvaluator;
use Alama\Arazzo\Runner\Evaluation\TypeCaster;
use Alama\Arazzo\Runner\Evaluation\Xpath\DomXpathEvaluator;
use Alama\Arazzo\Runner\Execution\Contracts\OutputExtractorInterface;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Spec\Step;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class ArazzoOutputExtractor implements OutputExtractorInterface
{
    private ?SelectorEvaluator $selectorEvaluator = null;

    public function __construct(
        private OpenApiOperationResolver $operationResolver,
        private ExpressionEvaluator $evaluator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        $outputs = [];
        foreach ($step->outputs as $outputName => $expression) {
            if ($expression instanceof Selector) {
                $outputs[$outputName] = $this->selectors()->evaluate($expression, $context, $step->stepId);

                continue;
            }

            if ($expression instanceof Expression) {
                $raw = trim($expression->raw);

                if (str_starts_with($raw, '$.')) {
                    $outputs[$outputName] = JsonPathEvaluator::evaluate($raw, is_array($responseBody) ? $responseBody : []);

                    continue;
                }

                $value = $this->evaluator->evaluate($expression, new EvaluationContext($context, $step->stepId, $document));
                $outputs[$outputName] = $this->castOutputAgainstResponseSchema($step, $context, $document, $expression, $value);
            } else {
                $outputs[$outputName] = $expression;
            }
        }

        return $outputs;
    }

    private function selectors(): SelectorEvaluator
    {
        return $this->selectorEvaluator ??= new SelectorEvaluator(new DomXpathEvaluator(), $this->evaluator);
    }

    private function castOutputAgainstResponseSchema(
        Step $step,
        WorkflowContext $context,
        ?ArazzoDocument $document,
        Expression $expression,
        mixed $value,
    ): mixed {
        if ($document === null) {
            return $value;
        }

        $ast = $expression->ast();
        if (!$ast instanceof StepRef || !$ast->part instanceof ResponsePart || $ast->part->httpPart !== 'body' || $ast->part->jsonPointer === null) {
            return $value;
        }

        try {
            $resolved = $this->operationResolver->resolve($step, $document);
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
        $leafSchema = $this->resolveSchemaAtPointer($schema instanceof Schema ? $schema : null, $ast->part->jsonPointer);

        return $this->castToSchemaType($value, $leafSchema);
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
