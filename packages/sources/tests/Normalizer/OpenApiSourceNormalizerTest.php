<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Normalizer;

use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Document\ResolvedOperation;
use Alama\Arazzo\Sources\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Sources\Normalizer\OpenApiSourceNormalizer;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;

function makeOpenApiSourceNormalizer(): OpenApiSourceNormalizer
{
    $resolver = new DefaultSourceResolver(fetchers: ['file' => new LocalFetcher()]);

    return new OpenApiSourceNormalizer(
        new OpenApiDocumentLoader($resolver),
        new OpenApiVersionDetector(),
        new OpenApi30Normalizer(),
        new OpenApi31Normalizer(),
    );
}

it('does not advertise support for non-openapi sources', function (): void {
    $normalizer = makeOpenApiSourceNormalizer();

    expect($normalizer->name())->toBe('openapi');
    expect($normalizer->priority())->toBe(0);
    expect($normalizer->supports(SourceType::Openapi))->toBeTrue();
    expect($normalizer->supports(SourceType::Asyncapi))->toBeFalse();
    expect($normalizer->supports(SourceType::Wsdl))->toBeFalse();
});

it('builds an operation index keyed by qualified name and json pointer', function (): void {
    $openapiJson = json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'servers' => [['url' => 'https://example.test']],
        'paths' => [
            '/pets' => [
                'get' => ['operationId' => 'listPets', 'responses' => ['200' => ['description' => 'OK']]],
                'post' => ['operationId' => 'createPet', 'responses' => ['201' => ['description' => 'Created']]],
            ],
        ],
    ]);

    $file = tempnam(sys_get_temp_dir(), 'oas_').'.json';
    file_put_contents($file, $openapiJson);

    try {
        $source = new SourceDescription('pets-api', $file, SourceType::Openapi);
        $index = makeOpenApiSourceNormalizer()->normalize($source, (string) $openapiJson);

        $qualified = $index['$sourceDescriptions.pets-api.listPets'] ?? null;
        expect($qualified)->toBeInstanceOf(ResolvedOperation::class);
        expect($qualified->sourceType())->toBe(SourceType::Openapi);
        expect($qualified->binding())->toBe('http');
        expect($qualified->normalized->method)->toBe('get');

        $pointer = $index['#/paths/pets/post'] ?? null;
        expect($pointer)->toBeInstanceOf(ResolvedOperation::class);
        expect($pointer->normalized->method)->toBe('post');
        expect($index['createPet'])->toBeInstanceOf(ResolvedOperation::class);
    } finally {
        @unlink($file);
    }
});

it('rejects Swagger 2.0 content instead of mis-routing it', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'swagger_').'.json';
    file_put_contents($file, json_encode(['swagger' => '2.0', 'info' => ['title' => 'L', 'version' => '1'], 'paths' => []]));

    try {
        $source = new SourceDescription('legacy', $file, SourceType::Openapi);
        makeOpenApiSourceNormalizer()->normalize($source, (string) file_get_contents($file));
    } finally {
        @unlink($file);
    }
})->throws(UnsupportedSourceVersionException::class, 'declares version \'2.0\', which is not supported');
