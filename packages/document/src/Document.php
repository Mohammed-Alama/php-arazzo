<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use RuntimeException;

/**
 * Public façade of the *document* package.
 *
 * Pure model work – no I/O, no cebe, no Guzzle.
 */
final class Document implements DocumentInterface
{
    public function __construct(
        private readonly ModelStack $model,
        private readonly ?OpenApiOperationResolver $operationResolver = null,
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
        assert($this->model->preflight !== null);

        return $this->model->preflight->validate($document);
    }

    public function preflightInputs(ArazzoDocument $document, string $workflowId, array $inputs): ValidationResult
    {
        assert($this->model->preflight !== null);

        return $this->model->preflight->validateInputs($document, $workflowId, $inputs);
    }

    public function resolveOperation(Step $step, ArazzoDocument $document): ResolvedOperation
    {
        $resolver = $this->operationResolver ?? $this->model->operationResolver;
        if ($resolver === null) {
            // Mimic the error message from OpenApiOperationResolver::resolve()
            if (!$step->target->operationId && !$step->target->operationPath) {
                throw new RuntimeException("Step '{$step->stepId}' must have either operationId or operationPath.");
            }
            throw new RuntimeException('No operation resolver available to resolve step operation.');
        }

        return $resolver->resolveModel($step, $document);
    }
}
