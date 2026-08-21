<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Exceptions\SchemaValidationException;
use Alama\Arazzo\Runner\Execution\Contracts\SchemaValidatorInterface;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;

class ArazzoSchemaValidator implements SchemaValidatorInterface
{
    public function __construct(
        private OpenApiOperationResolver $operationResolver,
    ) {
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        $schema = $this->findResponseSchema($step, $statusCode, $contentType, $document);

        if ($schema === null) {
            return; // No schema to validate against, so it's "valid" by default
        }

        $violations = SchemaValidator::validate($schema, $decodedBody);

        if ($violations !== []) {
            throw new SchemaValidationException($step->stepId, $violations);
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
        if ($document === null) {
            return null;
        }

        try {
            $resolved = $this->operationResolver->resolve($step, $document);

            return $resolved->cebeOperation;
        } catch (\RuntimeException) {
            return null;
        }
    }
}
