<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Normalizer;

use InvalidArgumentException;

class OpenApi30Normalizer implements OpenApiNormalizerInterface
{
    public function normalize(array $document, string $path, string $method): NormalizedOpenApiOperation
    {
        $method = strtolower($method);

        if (!isset($document['paths'][$path])) {
            throw new InvalidArgumentException("Path '{$path}' not found in document.");
        }
        $pathItem = $document['paths'][$path];

        if (!isset($pathItem[$method])) {
            throw new InvalidArgumentException("Method '{$method}' not found for path '{$path}'.");
        }
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
            method: $method,
            resolvedServerUrl: $resolvedServerUrl,
            parameters: $parameters,
            requestBodies: $requestBodies,
            responses: $responses,
        );
    }

    private function resolveServerUrl(array $document, array $pathItem, array $operation): ?string
    {
        if (isset($operation['servers']) && is_array($operation['servers']) && count($operation['servers']) > 0) {
            return $operation['servers'][0]['url'] ?? null;
        }

        if (isset($pathItem['servers']) && is_array($pathItem['servers']) && count($pathItem['servers']) > 0) {
            return $pathItem['servers'][0]['url'] ?? null;
        }

        if (isset($document['servers']) && is_array($document['servers']) && count($document['servers']) > 0) {
            return $document['servers'][0]['url'] ?? null;
        }

        return null; // Fallback or default could be '/' depending on context, but null is fine
    }

    private function resolveParameters(array $document, array $pathItem, array $operation): array
    {
        $pathParams = $pathItem['parameters'] ?? [];
        $opParams = $operation['parameters'] ?? [];

        // Normalize refs
        $pathParams = array_map(fn ($p) => $this->resolveLocalRef($document, $p), $pathParams);
        $opParams = array_map(fn ($p) => $this->resolveLocalRef($document, $p), $opParams);

        $merged = [];

        // Path parameters can be overridden by operation parameters if they have the same name and in.
        foreach ($pathParams as $param) {
            if (isset($param['name']) && isset($param['in'])) {
                $merged[$param['name'] . '|' . $param['in']] = $param;
            }
        }

        foreach ($opParams as $param) {
            if (isset($param['name']) && isset($param['in'])) {
                $merged[$param['name'] . '|' . $param['in']] = $param;
            }
        }

        return array_values($merged);
    }

    private function resolveRequestBodies(array $document, array $operation): array
    {
        if (!isset($operation['requestBody'])) {
            return [];
        }

        $requestBody = $this->resolveLocalRef($document, $operation['requestBody']);

        if (!isset($requestBody['content']) || !is_array($requestBody['content'])) {
            return [];
        }

        $bodies = [];
        foreach ($requestBody['content'] as $mediaType => $mediaTypeObj) {
            $bodies[$mediaType] = $mediaTypeObj; // We might want to resolve refs inside schema if needed, but for now just returning the media type structure
        }

        return $bodies;
    }

    private function resolveResponses(array $document, array $operation): array
    {
        if (!isset($operation['responses']) || !is_array($operation['responses'])) {
            return [];
        }

        $responses = [];
        foreach ($operation['responses'] as $status => $response) {
            $responses[(string) $status] = $this->resolveLocalRef($document, $response);
        }

        return $responses;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function resolveLocalRef(array $document, array $item): array
    {
        if (!isset($item['$ref'])) {
            return $item;
        }

        $ref = $item['$ref'];

        if (!is_string($ref) || !str_starts_with($ref, '#/')) {
            // We only handle local JSON pointers starting with #/
            return $item;
        }

        $pointer = substr($ref, 2); // remove #/
        $parts = explode('/', $pointer);

        $current = $document;
        foreach ($parts as $part) {
            // Unescape JSON pointer: ~1 -> /, ~0 -> ~
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);

            if (!isset($current[$part])) {
                // If we can't resolve, return the original item (or throw?)
                return $item;
            }
            $current = $current[$part];
        }

        if (!is_array($current)) {
            return $item;
        }

        // Merge resolved item with any original properties in the reference object (except $ref)
        // OpenAPI 3.1 allows overriding, OpenAPI 3.0 mostly ignores siblings, but merging is safe.
        $result = array_merge($current, $item);
        unset($result['$ref']);

        return $result;
    }
}
