<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\RawDocument;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Alama\Arazzo\Document\Parser\Decoders\NativeJsonDecoder;
use Alama\Arazzo\Document\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Document\Parser\Loader;
use Alama\Arazzo\Document\Parser\Parser;
use Alama\Arazzo\Document\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Document\Resolver\Fetchers\HttpFetcher;
use Alama\Arazzo\Document\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Document\Validator\RuleSet;
use Alama\Arazzo\Document\Validator\Validator;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Concrete document facade.
 *
 * A thin, self-contained object that hides the document pipeline (load,
 * parse, source resolution, OpenAPI normalization, validation) behind a few
 * entry points. Built-in collaborators keep it usable with zero wiring.
 */
final class Document implements DocumentInterface
{
    private Loader $loader;

    private Parser $parser;

    private Validator $validator;

    private PreflightValidator $preflight;

    private ExpressionEngineInterface $engine;

    private SourceRegistry $sources;

    private OpenApiOperationResolver $operations;

    private OpenApiVersionDetector $versionDetector;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $httpFactory = null,
        ?SourceRegistry $sources = null,
    ) {
        $client = $httpClient ?? new Client();
        $factory = $httpFactory ?? new HttpFactory();

        $this->loader = new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
        $this->parser = new Parser();
        $this->engine = new ExpressionEngine();
        $this->validator = new Validator($this->engine, RuleSet::default($this->engine));
        $this->versionDetector = new OpenApiVersionDetector();

        $this->sources = $sources ?? new SourceRegistry(new DefaultSourceResolver([
            'http' => new HttpFetcher($client, $factory),
            'https' => new HttpFetcher($client, $factory),
            'file' => new LocalFetcher(),
        ]));

        $this->operations = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($this->sources),
            $this->versionDetector,
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        $this->preflight = new PreflightValidator($this->sources, $this->operations, $this->engine);
    }

    public function load(string $path): ArazzoDocument
    {
        return $this->parser->parse($this->loader->load($path));
    }

    public function parse(RawDocument $raw): ArazzoDocument
    {
        return $this->parser->parse($raw);
    }

    public function validate(ArazzoDocument $document): ValidationResult
    {
        return $this->validator->validate($document);
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
        return $this->operations->resolve($step, $document);
    }
}
