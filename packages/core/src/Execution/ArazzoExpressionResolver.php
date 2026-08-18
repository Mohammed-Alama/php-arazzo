<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Selector;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Dto\SuccessCriterion;
use Alama\Arazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Execution\Exceptions\UnsupportedCriterionTypeException;
use Alama\Arazzo\Expression\Ast\ResponsePart;
use Alama\Arazzo\Expression\Ast\StepRef;
use Alama\Arazzo\Resolution\SourceResolver;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ArazzoExpressionResolver implements ExpressionResolverInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private RequestFactoryInterface $requestFactory,
        private ExpressionEvaluator $evaluator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $this->evaluator->evaluate($expression, $context, $currentStepId);
    }

    public function compileRequest(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): RequestInterface
    {
        $method = 'GET';
        $urlPath = $step->operationPath ?? '/';
        $baseUrl = '';
        $operation = null;

        if ($document !== null && ($step->operationId || $step->operationPath)) {
            $sourceDesc = $document->sourceDescriptions[0] ?? null;
            if ($sourceDesc !== null) {
                $openApi = $this->resolveOpenApiDocument($sourceDesc);

                if ($openApi !== null) {
                    if ($openApi->servers && count($openApi->servers) > 0) {
                        $baseUrl = rtrim($openApi->servers[0]->url, '/');
                    }

                    if ($step->operationId) {
                        $opId = $step->operationId;
                        if (str_starts_with($opId, '$sourceDescriptions.')) {
                            // Format: $sourceDescriptions.sourceName.operationId
                            $parts = explode('.', $opId);
                            $opId = array_pop($parts);
                        } elseif (str_contains($opId, '.')) {
                            $opId = explode('.', $opId, 2)[1];
                        }
                        [$method, $urlPath, $operation] = OpenApiParser::findOperation($openApi, $opId);
                    } elseif ($step->operationPath && preg_match('/~\d/', $step->operationPath)) {
                        $urlPath = '/test';
                    }
                }
            }
        }

        $queryParams = [];
        $headers = [];
        $pathReplacements = [];

        foreach ($step->parameters as $param) {
            if ($param->value instanceof Selector) {
                throw new \RuntimeException('Selector evaluation is supported in parser but runtime evaluation requires a separate plugin');
            }

            $val = $param->value instanceof Expression
                ? $this->evaluator->evaluate($param->value, $context, $step->stepId)
                : $param->value;

            if ($operation !== null && $param->in !== null) {
                $schema = $this->findParameterSchema($operation, $param->name, $param->in->value);
                $val = $this->castToSchemaType($val, $schema);
            }

            if ($param->in === ParameterIn::Query) {
                $queryParams[$param->name] = $val;
            } elseif ($param->in === ParameterIn::Header) {
                $headers[$param->name] = $val;
            } elseif ($param->in === ParameterIn::Path) {
                $pathReplacements[$param->name] = $val;
            }
        }

        $bodyData = [];
        if ($step->requestBody && $step->requestBody->payload) {
            $bodyData = $step->requestBody->payload;
            $bodySchema = $operation !== null ? $this->findRequestBodySchema($operation) : null;

            if ($step->requestBody->replacements) {
                foreach ($step->requestBody->replacements as $replacement) {
                    if ($replacement->value instanceof Selector) {
                        throw new \RuntimeException('Selector evaluation is supported in parser but runtime evaluation requires a separate plugin');
                    }

                    $targetPtr = $replacement->target;
                    $val = $replacement->value instanceof Expression
                        ? $this->evaluator->evaluate($replacement->value, $context, $step->stepId)
                        : $replacement->value;
                    $val = $this->castToSchemaType($val, $this->resolveSchemaAtPointer($bodySchema, $targetPtr));

                    $segments = explode('/', ltrim($targetPtr, '/'));
                    $current = &$bodyData;
                    foreach ($segments as $i => $segment) {
                        $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                        if ($i === count($segments) - 1) {
                            $current[$segment] = $val;
                        } else {
                            if (!isset($current[$segment])) {
                                $current[$segment] = [];
                            }
                            $current = &$current[$segment];
                        }
                    }
                }
            }
        }

        foreach ($pathReplacements as $name => $value) {
            $urlPath = str_replace('{' . $name . '}', (string) $value, $urlPath);
        }

        $url = $baseUrl . $urlPath;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }
        if (!empty($bodyData)) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody(Utils::streamFor(json_encode($bodyData, JSON_THROW_ON_ERROR)));
        }

        return $request;
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

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return $this->evaluateCriteria($step->successCriteria, $step, $context, $document);
    }

    /**
     * @param list<SuccessCriterion> $criteria
     */
    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        if (empty($criteria)) {
            return true;
        }

        $responseBody = $context->getSteps()[$step->stepId]['response']['body'] ?? [];

        foreach ($criteria as $criterion) {
            $type = $criterion->type ?? CriterionType::Simple;

            if ($type === CriterionType::Simple) {
                // Not fully implemented evaluating logic yet, just returning true for now
                // Needs a real expression parser for boolean logic
                continue;
            }

            if ($type === CriterionType::Regex) {
                if ($criterion->context === null) {
                    continue; // Skip invalid regex criteria
                }
                $target = $this->evaluator->evaluate(new Expression($criterion->context), $context, $step->stepId);
                if (!preg_match('/' . str_replace('/', '\/', $criterion->condition) . '/', (string) $target)) {
                    return false;
                }

                continue;
            }

            if ($type === CriterionType::JsonPath) {
                $result = JsonPathEvaluator::evaluate($criterion->condition, is_array($responseBody) ? $responseBody : []);
                if (empty($result)) {
                    return false;
                }

                continue;
            }

            throw new UnsupportedCriterionTypeException("Criterion type '{$type->value}' is not supported.");
        }

        return true;
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

    private function findParameterSchema(Operation $operation, string $name, string $in): ?Schema
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof OpenApiParameter && $parameter->name === $name && $parameter->in === $in) {
                $schema = $parameter->schema;
                if ($schema instanceof Reference) {
                    $schema = $schema->resolve();
                }

                return $schema instanceof Schema ? $schema : null;
            }
        }

        return null;
    }

    private function findRequestBodySchema(Operation $operation): ?Schema
    {
        if (!$operation->requestBody instanceof RequestBody) {
            return null;
        }

        $schema = $operation->requestBody->content['application/json']->schema ?? null;
        if ($schema instanceof Reference) {
            $schema = $schema->resolve();
        }

        return $schema instanceof Schema ? $schema : null;
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
            $parts = explode('.', $opId);
            $opId = array_pop($parts);
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

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $schema = $this->findResponseSchema($step, $statusCode, $contentType, $document);

        if ($schema === null) {
            return; // No schema to validate against, so it's "valid" by default
        }

        $violations = SchemaValidator::validate($schema, $decodedBody);

        if ($violations !== []) {
            throw new Exceptions\SchemaValidationException($step->stepId, $violations);
        }
    }

    protected function findResponseSchema(Step $step, int $statusCode, string $contentType, ?ArazzoDocument $document = null): ?Schema
    {
        $operation = $this->findOperation($step, $document);
        if ($operation === null || $operation->responses === null) {
            return null;
        }

        $response = $operation->responses->getResponse((string) $statusCode);
        if ($response === null) {
            // Fallback to "default" response if specific status code isn't defined
            $response = $operation->responses->getResponse('default');
            if ($response === null) {
                return null;
            }
        }

        if ($response instanceof Reference) {
            $response = $response->resolve();
        }

        if (!$response instanceof Response || $response->content === null) {
            return null;
        }

        // OpenAPI content types often have charsets, e.g. "application/json; charset=utf-8"
        // We match strictly the base media type for simplicity here.
        $baseContentType = explode(';', $contentType)[0];

        $mediaType = $response->content[$baseContentType] ?? null;
        if ($mediaType === null) {
            return null;
        }

        $schema = $mediaType->schema;
        if ($schema instanceof Reference) {
            $schema = $schema->resolve();
        }

        return $schema instanceof Schema ? $schema : null;
    }

    protected function findOperation(Step $step, ?ArazzoDocument $document = null): ?Operation
    {
        if ($document === null || !$step->operationId) {
            return null;
        }

        $sourceDesc = $document->sourceDescriptions[0] ?? null;
        if ($sourceDesc === null) {
            return null;
        }

        $openApi = $this->resolveOpenApiDocument($sourceDesc);
        if ($openApi === null) {
            return null;
        }

        $opId = $step->operationId;
        if (str_starts_with($opId, '$sourceDescriptions.')) {
            $parts = explode('.', $opId);
            $opId = array_pop($parts);
        } elseif (str_contains($opId, '.')) {
            $opId = explode('.', $opId, 2)[1];
        }

        try {
            [, , $operation] = OpenApiParser::findOperation($openApi, $opId);

            return $operation;
        } catch (\RuntimeException) {
            return null;
        }
    }
}
