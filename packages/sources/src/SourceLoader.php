<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Contracts\Interfaces\SourceNormalizerRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;

/**
 * Entry point for the Sources package – pure source resolution and normalization.
 * No generic document pipeline; only operations that need HTTP / cebe / OpenAPI live here.
 */
final class SourceLoader
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly OpenApiOperationResolver $operations,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly PreflightValidator $preflight,
        private readonly SourceNormalizerRegistryInterface $normalizers,
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

    /**
     * Normalize every described source and return an operation index.
     *
     * Keys mirror the resolver's accepted references (operationId,
     * $sourceDescriptions.<name>.<operationId>, and #/paths/.../method).
     *
     * @return array<string, ResolvedOperation>
     */
    public function normalizeSources(ArazzoDocument $document, string $basePath = ''): array
    {
        $index = [];
        foreach ($document->sourceDescriptions as $source) {
            $normalizer = $this->normalizers->get($source->type);
            if ($normalizer === null) {
                error_log('No normalizer for source type: '.$source->type->value);

                continue;
            }
            $resolved = $this->sources->resolve($source, $basePath);
            error_log('Resolved source: '.$source->name.', content keys: '.implode(', ', array_keys($resolved->content)));
            $rawContent = json_encode($resolved->content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            /** @var array<string, ResolvedOperation> $normalized */
            $normalized = $normalizer->normalize($source, $rawContent, $document);
            error_log('Normalized index keys: '.implode(', ', array_keys($normalized)));
            $index += $normalized;
        }

        return $index;
    }
}
