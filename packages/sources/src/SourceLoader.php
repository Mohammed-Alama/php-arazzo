<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;

/**
 * Pure source‑loader – **no** generic document pipeline.
 * Only the operations that really need HTTP / cebe / OpenAPI live here.
 */
final class SourceLoader
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly OpenApiOperationResolver $operations,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly PreflightValidator $preflight,
    ) {}

    /* --------------------------------------------------------------- *
     *  Source‑specific operations – the *only* public API of this class *
     * --------------------------------------------------------------- */
    public function resolveSource(SourceDescription $source, string $basePath): SourceDocument
    {
        return $this->sources->resolve($source, $basePath);
    }

    /** @param array<string,mixed> $document */
    public function detectOpenApiVersion(array $document): string
    {
        return $this->versionDetector->detect($document);
    }

    /** Returns a handle that carries the cebe OpenApi + Operation objects */
    public function resolveHandle(Step $step, ArazzoDocument $document): OpenApiOperationHandle
    {
        return $this->operations->resolve($step, $document);
    }

    /* --------------------------------------------------------------- *
     *  Optional – source‑level preflight that uses source resolution   *
     * --------------------------------------------------------------- */
    public function preflightSource(ArazzoDocument $document): ValidationResult
    {
        return $this->preflight->validate($document);
    }
}
