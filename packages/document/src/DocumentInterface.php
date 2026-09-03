<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Document\Parser\Exceptions\LoaderException;
use Alama\Arazzo\Document\Parser\Exceptions\ParserException;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;

/**
 * Entry-point seam for the document package.
 *
 * Downstream packages depend on this interface (never the concrete
 * {@see Document} or the parse/normalize/validate internals).
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
}
