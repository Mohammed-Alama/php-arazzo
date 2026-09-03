<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

interface ResponseValidatorInterface
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
