<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\Exceptions\SchemaValidationException;

interface SchemaValidatorInterface
{
    /**
     * Validates a decoded response body against the step's OpenAPI schema.
     * Throws SchemaValidationException if validation fails.
     * Does nothing if no matching schema is found.
     *
     * @throws SchemaValidationException
     */
    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void;
}
