<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\Contracts\ExpressionEvaluatorInterface;
use Alama\Arazzo\Runner\Contracts\OutputExtractorInterface;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

class ArazzoOutputExtractor implements OutputExtractorInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private ExpressionEvaluatorInterface $evaluator,
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
                throw new \RuntimeException('Selector evaluation is supported in parser but runtime evaluation requires a separate plugin');
            }

            if ($expression instanceof Expression) {
                $raw = trim($expression->raw);

                if (str_starts_with($raw, '$.')) {
                    $outputs[$outputName] = JsonPathEvaluator::evaluate($raw, is_array($responseBody) ? $responseBody : []);

                    continue;
                }

                $value = $this->evaluator->evaluate($expression, $context, $step->stepId);
                $outputs[$outputName] = $this->castOutputAgainstResponseSchema($step, $context, $document, $expression, $value);
            } else {
                $outputs[$outputName] = $expression;
            }
        }

        return $outputs;
    }

    private function castOutputAgainstResponseSchema(
        Step $step,
        WorkflowContext $context,
        ?ArazzoDocument $document,
        Expression $expression,
        mixed $value,
    ): mixed {
        if ($document === null || !$step->operationId) {
            return $value;
        }

        $ast = $expression->ast();
        if (!$ast instanceof StepRef || !$ast->part instanceof ResponsePart || $ast->part->httpPart !== 'body' || $ast->part->jsonPointer === null) {
            return $value;
        }

        $sourceDesc = $document->sourceDescriptions[0] ?? null;
        if ($sourceDesc === null) {
            return $value;
        }

        $openApi = $this->resolveOpenApiDocument($sourceDesc);
        if ($openApi === null) {
            return $value;
        }

        $opId = $step->operationId;
        if (str_starts_with($opId, '$sourceDescriptions.')) {
            $parts = explode('.', $opId, 3);
            $opId = $parts[2] ?? '';
        } elseif (str_contains($opId, '.')) {
            $opId = explode('.', $opId, 2)[1];
        }

        try {
            [, , $operation] = OpenApiParser::findOperation($openApi, $opId);
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

    private function resolveOpenApiDocument(SourceDescription $sourceDesc): ?OpenApi
    {
        $resolvedSource = $this->sourceResolver->resolve($sourceDesc, getcwd() ?: '');
        $extracted = $resolvedSource->extract('');

        if ($extracted instanceof OpenApi) {
            return $extracted;
        }

        $json = json_encode($extracted);
        if ($json === false) {
            return null;
        }

        try {
            return Reader::readFromJson($json);
        } catch (Throwable) {
            return null;
        }
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
