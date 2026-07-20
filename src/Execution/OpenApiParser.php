<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use RuntimeException;

class OpenApiParser
{
    /**
     * @return array{0: string, 1: string} [method, url_path]
     */
    public static function findOperation(OpenApi $openApi, string $operationId): array
    {
        foreach ($openApi->paths as $path => $pathItem) {
            /** @var PathItem $pathItem */
            $methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];
            foreach ($methods as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method;
                if ($operation !== null && $operation->operationId === $operationId) {
                    return [strtoupper($method), (string) $path];
                }
            }
        }
        
        throw new RuntimeException("Operation '{$operationId}' not found in OpenAPI specification.");
    }
}
