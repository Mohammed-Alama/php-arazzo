<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Document\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use Alama\Arazzo\Document\Resolver\Exceptions\UnresolvableReferenceException;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use InvalidArgumentException;
use RuntimeException;

/**
 * Entry-point seam for the document package.
 *
 * Downstream packages depend on this interface (never the concrete
 * {@see Document} or the parse/normalize/validate internals). It exposes the
 * full capability set: loading/parsing documents, static validation,
 * preflight validation, source resolution and OpenAPI operation resolution
 * (with version detection).
 */
interface DocumentInterface
{
    /**
     * Load and parse an Arazzo document from a YAML/JSON file path.
     *
     * @throws LoaderException
     * @throws ParserException
     */
    public function load(string $path): ArazzoDocument;

    /**
     * Parse an already-decoded raw document.
     *
     * @throws ParserException
     */
    public function parse(RawDocument $raw): ArazzoDocument;

    /**
     * Static (RuleSet) validation of a parsed document.
     */
    public function validate(ArazzoDocument $document): ValidationResult;

    /**
     * Preflight validation that also audits operation-targeted steps and
     * declared inputs against their sources.
     */
    public function preflight(ArazzoDocument $document): ValidationResult;

    /**
     * Validate supplied runtime inputs against a workflow's declared inputs
     * JSON Schema (2020-12) before any side effect.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function preflightInputs(ArazzoDocument $document, string $workflowId, array $inputs): ValidationResult;

    /**
     * Resolve a source description into its fetched and decoded document.
     *
     * @throws UnresolvableReferenceException
     */
    public function resolveSource(SourceDescription $source, string $basePath): SourceDocument;

    /**
     * Detect the OpenAPI/Swagger version of a decoded source document.
     *
     * @param  array<string, mixed>  $document
     * @return string One of '2.0', '3.0' or '3.1'.
     *
     * @throws InvalidArgumentException
     */
    public function detectOpenApiVersion(array $document): string;

    /**
     * Resolve a step's operation target against its source and normalize the
     * operation for execution.
     *
     * @throws RuntimeException
     */
    public function resolveOperation(Step $step, ArazzoDocument $document): ResolvedOperation;
}
