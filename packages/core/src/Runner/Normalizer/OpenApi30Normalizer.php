<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

use InvalidArgumentException;

class OpenApi30Normalizer implements OpenApiNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation
    {
        $method = strtolower($method);

        if (!isset($document['paths']) || !is_array($document['paths']) || !isset($document['paths'][$path]) || !is_array($document['paths'][$path])) {
            throw new InvalidArgumentException("Path '{$path}' not found in document.");
        }
        /** @var array<string, mixed> $pathItem */
        $pathItem = $document['paths'][$path];

        if (!isset($pathItem[$method]) || !is_array($pathItem[$method])) {
            throw new InvalidArgumentException("Method '{$method}' not found for path '{$path}'.");
        }
        /** @var array<string, mixed> $operation */
        $operation = $pathItem[$method];

        // 1. Resolve Server URL
        $resolvedServerUrl = $this->resolveServerUrl($document, $pathItem, $operation);

        // 2. Resolve Parameters
        $parameters = $this->resolveParameters($document, $pathItem, $operation);

        // 3. Resolve Request Bodies
        $requestBodies = $this->resolveRequestBodies($document, $operation);

        // 4. Resolve Responses
        $responses = $this->resolveResponses($document, $operation);

        return new NormalizedOpenApiOperation(
            path: $path,
            method: $method,
            resolvedServerUrl: $resolvedServerUrl,
            pathParameters: $parameters['path'],
            queryParameters: $parameters['query'],
            headerParameters: $parameters['header'],
            cookieParameters: $parameters['cookie'],
            requestBodies: $requestBodies,
            responses: $responses,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $pathItem
     * @param  array<string, mixed>  $operation
     */
    private function resolveServerUrl(array $document, array $pathItem, array $operation): ?string
    {
        if (isset($operation['servers']) && is_array($operation['servers']) && count($operation['servers']) > 0) {
            return is_array($operation['servers'][0]) && isset($operation['servers'][0]['url']) && is_string($operation['servers'][0]['url']) ? $operation['servers'][0]['url'] : null;
        }

        if (isset($pathItem['servers']) && is_array($pathItem['servers']) && count($pathItem['servers']) > 0) {
            return is_array($pathItem['servers'][0]) && isset($pathItem['servers'][0]['url']) && is_string($pathItem['servers'][0]['url']) ? $pathItem['servers'][0]['url'] : null;
        }

        if (isset($document['servers']) && is_array($document['servers']) && count($document['servers']) > 0) {
            return is_array($document['servers'][0]) && isset($document['servers'][0]['url']) && is_string($document['servers'][0]['url']) ? $document['servers'][0]['url'] : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $pathItem
     * @param  array<string, mixed>  $operation
     * @return array{path: array<string, array<string, mixed>>, query: array<string, array<string, mixed>>, header: array<string, array<string, mixed>>, cookie: array<string, array<string, mixed>>}
     */
    private function resolveParameters(array $document, array $pathItem, array $operation): array
    {
        /** @var array<array<string, mixed>> $pathParams */
        $pathParams = isset($pathItem['parameters']) && is_array($pathItem['parameters']) ? $pathItem['parameters'] : [];
        /** @var array<array<string, mixed>> $opParams */
        $opParams = isset($operation['parameters']) && is_array($operation['parameters']) ? $operation['parameters'] : [];

        $pathParams = array_map(fn (array $p): array => $this->resolveLocalRef($document, $p), $pathParams);
        $opParams = array_map(fn (array $p): array => $this->resolveLocalRef($document, $p), $opParams);

        $merged = [];

        foreach ($pathParams as $param) {
            if (isset($param['name'], $param['in']) && is_string($param['name']) && is_string($param['in'])) {
                $merged[$param['name'].'|'.$param['in']] = $param;
            }
        }

        foreach ($opParams as $param) {
            if (isset($param['name'], $param['in']) && is_string($param['name']) && is_string($param['in'])) {
                $merged[$param['name'].'|'.$param['in']] = $param;
            }
        }

        $grouped = [
            'path' => [],
            'query' => [],
            'header' => [],
            'cookie' => [],
        ];

        foreach ($merged as $param) {

            if (isset($grouped[$param['in']])) {

                $grouped[$param['in']][$param['name']] = $param;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolveLocalRef(array $document, array $item): array
    {
        if (!isset($item['$ref'])) {
            return $item;
        }

        $ref = $item['$ref'];

        if (!is_string($ref) || !str_starts_with($ref, '#/')) {
            return $item;
        }

        $pointer = substr($ref, 2);
        $parts = explode('/', $pointer);

        $current = $document;
        foreach ($parts as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (!is_array($current) || !isset($current[$part])) {
                return $item;
            }
            $current = $current[$part];
        }

        if (!is_array($current)) {
            return $item;
        }

        $result = array_merge($current, $item);
        unset($result['$ref']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function resolveRequestBodies(array $document, array $operation): array
    {
        if (!isset($operation['requestBody']) || !is_array($operation['requestBody'])) {
            return [];
        }

        /** @var array<string, mixed> $reqBody */
        $reqBody = $operation['requestBody'];
        $requestBody = $this->resolveLocalRef($document, $reqBody);

        if (!isset($requestBody['content']) || !is_array($requestBody['content'])) {
            return [];
        }

        $bodies = [];
        foreach ($requestBody['content'] as $mediaType => $mediaTypeObj) {
            $bodies[(string) $mediaType] = $mediaTypeObj;
        }

        return $bodies;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function resolveResponses(array $document, array $operation): array
    {
        if (!isset($operation['responses']) || !is_array($operation['responses'])) {
            return [];
        }

        $responses = [];
        foreach ($operation['responses'] as $status => $response) {
            $responses[(string) $status] = $response;
        }

        return $responses;
    }
}
