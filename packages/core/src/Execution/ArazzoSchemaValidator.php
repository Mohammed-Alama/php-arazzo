<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\Contracts\SchemaValidatorInterface;
use Alama\Arazzo\Resolution\SourceResolver;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use Throwable;

class ArazzoSchemaValidator implements SchemaValidatorInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
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
}
