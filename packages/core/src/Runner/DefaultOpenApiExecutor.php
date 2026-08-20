<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Resolver\SourceResolver;
use Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class DefaultOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ?LoggerInterface $logger = null,
    ) {
    }
    
    public function execute(
        SourceDescription $source,
        string $operationIdOrPath,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null
    ): ResponseInterface {
        $openApi = $this->resolveOpenApiDocument($source);
        if ($openApi === null) {
            throw new \RuntimeException('Failed to resolve OpenAPI document');
        }

        $baseUrl = '';
        if ($openApi->servers && count($openApi->servers) > 0) {
            $baseUrl = rtrim($openApi->servers[0]->url, '/');
        }

        $opId = $operationIdOrPath;
        if (str_starts_with($opId, '$sourceDescriptions.')) {
            $parts = explode('.', $opId, 3);
            $opId = $parts[2] ?? '';
        } elseif (str_contains($opId, '.')) {
            $opId = explode('.', $opId, 2)[1];
        }

        $method = 'GET';
        $urlPath = '/';
        $operation = null;
        
        if ($openApi !== null) {
            [$method, $urlPath, $operation] = OpenApiParser::findOperation($openApi, $opId);
        }

        if ($operation !== null) {
            foreach ($payload->auto as $name => $value) {
                $in = $this->findParameterLocation($operation, $name);
                if ($in === 'path') {
                    $payload->path[$name] = $value;
                } elseif ($in === 'header') {
                    $payload->header[$name] = $value;
                } else {
                    // Default to query if unknown, since Arazzo says "query" is the most common default
                    $payload->query[$name] = $value;
                }
            }
            
            $payload->path = $this->castParameters($operation, $payload->path, 'path');
            $payload->query = $this->castParameters($operation, $payload->query, 'query');
            $payload->header = $this->castParameters($operation, $payload->header, 'header');
        } else {
            // Fallback if operation not found in schema
            $payload->query = array_merge($payload->query, $payload->auto);
        }

        foreach ($payload->path as $name => $value) {
            $urlPath = str_replace('{' . $name . '}', (string) $value, $urlPath);
        }

        $url = $baseUrl . $urlPath;
        if (!empty($payload->query)) {
            $url .= '?' . http_build_query($payload->query);
        }

        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($payload->header as $k => $v) {
            $request = $request->withHeader($k, (string) $v);
        }

        if ($payload->body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody(Utils::streamFor(json_encode($payload->body, JSON_THROW_ON_ERROR)));
        }

        if ($requestInterceptor !== null) {
            $request = $requestInterceptor($request);
        }

        return $this->httpClient->sendRequest($request);
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
    
    private function findParameterLocation(Operation $operation, string $name): ?string
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof OpenApiParameter && $parameter->name === $name) {
                return $parameter->in;
            }
        }
        return null;
    }
    
    private function castParameters(Operation $operation, array $params, string $in): array
    {
        $result = [];
        foreach ($params as $name => $value) {
            $schema = $this->findParameterSchema($operation, $name, $in);
            $result[$name] = $this->castToSchemaType($value, $schema);
        }
        return $result;
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
        } catch (\Exception) {
            return $value;
        }
    }
}
