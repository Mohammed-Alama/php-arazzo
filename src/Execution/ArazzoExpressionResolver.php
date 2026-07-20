<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\RequestBody;
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
                        $opId = str_contains($step->operationId, '.')
                            ? explode('.', $step->operationId, 2)[1]
                            : $step->operationId;
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
                    $targetPtr = $replacement->target;
                    $val = $this->evaluator->evaluate($replacement->value, $context, $step->stepId);
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
            $request = $request->withBody(Utils::streamFor(json_encode($bodyData)));
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        return [];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    private function resolveOpenApiDocument(SourceDescription $sourceDesc): ?OpenApi
    {
        $resolvedSource = $this->sourceResolver->resolve($sourceDesc, getcwd() ?: '');
        $extracted = $resolvedSource->extract('');

        if ($extracted instanceof OpenApi) {
            return $extracted;
        }

        try {
            return Reader::readFromJson(json_encode($extracted));
        } catch (Throwable) {
            return null;
        }
    }

    private function findParameterSchema(Operation $operation, string $name, string $in): ?Schema
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof OpenApiParameter && $parameter->name === $name && $parameter->in === $in) {
                return $parameter->schema;
            }
        }

        return null;
    }

    private function findRequestBodySchema(Operation $operation): ?Schema
    {
        if (!$operation->requestBody instanceof RequestBody) {
            return null;
        }

        return $operation->requestBody->content['application/json']->schema ?? null;
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
