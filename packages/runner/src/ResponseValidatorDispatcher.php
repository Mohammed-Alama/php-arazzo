<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Exceptions\SchemaValidationException;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

/**
 * Dispatches response validation across registered ResponseValidatorInterface plugins.
 *
 * After Phase F1, protocol-specific validators (HTTP/OpenAPI, SOAP/XSD, RPC/proto)
 * are registered here and the in-core validator becomes the fallback.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ResponseValidatorDispatcher
{
    /** @param list<ResponseValidatorInterface> $validators */
    public function __construct(
        private array $validators,
    ) {}

    /**
     * @throws SchemaValidationException
     */
    public function validate(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void
    {
        foreach ($this->validators as $validator) {
            $validator->validateResponseSchema($step, $statusCode, $contentType, $decodedBody, $document);
        }
    }
}
