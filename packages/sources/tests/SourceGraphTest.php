<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\SourceDocument;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Sources\Resolver\SourceRegistry;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Sources\SourceLoader;

it('builds a document that satisfies the port without inline http construction', function (): void {
    $document = SourceGraph::document();

    expect($document)->toBeInstanceOf(DocumentInterface::class);
});

it('honours an injected source registry', function (): void {
    $canned = new SourceDocument('injected', SourceType::Openapi, 'memory://injected', ['openapi' => '3.0.0']);

    $registry = new SourceRegistry(new class($canned) implements SourceResolver
    {
        public function __construct(private readonly SourceDocument $canned) {}

        public function resolve(SourceDescription $source, string $basePath): SourceDocument
        {
            return $this->canned;
        }
    });

    $loader = SourceGraph::loader(registry: $registry);

    $resolved = $loader->resolveSource(
        new SourceDescription('api', 'https://example.test/openapi.json', SourceType::Openapi),
        'memory://',
    );

    expect($resolved)->toBe($canned);
});

it('keeps the default registry usable when none is injected', function (): void {
    $loader = SourceGraph::loader();

    $resolved = $loader->resolveSource(
        new SourceDescription('api', __DIR__.'/fixtures/document/openapi30.yaml', SourceType::Openapi),
        __DIR__.'/fixtures/document',
    );

    expect($resolved->type)->toBe(SourceType::Openapi);
});

it('does not construct a guzzle client inside the source loader implementation', function (): void {
    $source = file_get_contents(__DIR__.'/../src/SourceLoader.php');

    expect($source)->not->toContain('GuzzleHttp\Client');
});

it('builds a loader from the default source resolver without arguments', function (): void {
    $loader = SourceGraph::loader(registry: new SourceRegistry(new DefaultSourceResolver([])));

    expect($loader)->toBeInstanceOf(SourceLoader::class);
});
