<?php

declare(strict_types=1);

namespace Alama\Arazzo\Sources;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Document\ModelStack;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\Validator\PreflightValidator;

/**
 * Concrete document facade.
 *
 * A thin, self-contained object that hides the document pipeline (load,
 * parse, source resolution, OpenAPI normalization, validation) behind a few
 * entry points. Built-in collaborators keep it usable with zero wiring.
 */
final class Document implements DocumentInterface
{
    public function __construct(
        private readonly ModelStack $model,
        private readonly SourceRegistry $sources,
        private readonly OpenApiOperationResolver $operations,
        private readonly OpenApiVersionDetector $versionDetector,
        private readonly PreflightValidator $preflight,
    ) {}

    public function load(string $path): ArazzoDocument
    {
        return $this->model->parser->parse($this->model->loader->load($path));
    }

    public function parse(RawDocument $raw): ArazzoDocument
    {
        return $this->model->parser->parse($raw);
    }

    public function validate(ArazzoDocument $document): ValidationResult
    {
        return $this->model->validator->validate($document);
    }

    public function preflight(ArazzoDocument $document): ValidationResult
    {
        return $this->preflight->validate($document);
    }

    public function preflightInputs(ArazzoDocument $document, string $workflowId, array $inputs): ValidationResult
    {
        return $this->preflight->validateInputs($document, $workflowId, $inputs);
    }

    public function resolveSource(SourceDescription $source, string $basePath): SourceDocument
    {
        return $this->sources->resolve($source, $basePath);
    }

    public function detectOpenApiVersion(array $document): string
    {
        return $this->versionDetector->detect($document);
    }

    public function resolveOperation(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        return $this->operations->resolveModel($step, $document);
    }
}
