<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Protocol\Http\Normalizer;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\SourceType;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\SourceDescription;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Protocol\Http\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Protocol\Http\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Protocol\Http\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Protocol\Http\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Sources\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Sources\Resolver\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Sources\Resolver\Fetchers\LocalFetcher;

it('rejects Swagger 2.0 sources with a typed error instead of mis-routing them to the 3.0 normalizer', function () {
    $swaggerJson = json_encode([
        'swagger' => '2.0',
        'info' => ['title' => 'Legacy', 'version' => '1.0'],
        'basePath' => '/v2',
        'paths' => [
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ]);

    $swaggerFile = tempnam(sys_get_temp_dir(), 'swagger_').'.json';
    file_put_contents($swaggerFile, $swaggerJson);

    try {
        $sourceResolver = new DefaultSourceResolver(fetchers: ['file' => new LocalFetcher()]);
        $resolver = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($sourceResolver),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        $document = new ArazzoDocument(
            arazzo: '1.0.1',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [new SourceDescription('legacy-api', $swaggerFile, SourceType::Openapi)],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );

        $step = StepFactory::http(
            stepId: 'step1',
            description: null,
            flow: new StepFlow(),
            io: new StepIo(),
            operationId: 'legacy-api.listPets',
        );

        $resolver->resolve($step, $document);
    } finally {
        @unlink($swaggerFile);
    }
})->throws(UnsupportedSourceVersionException::class, 'declares version \'2.0\', which is not supported');

it('resolves a supported operation into both the cebe handle and the vendor-free model', function (): void {
    $openApiJson = json_encode([
        'openapi' => '3.0.3',
        'info' => ['title' => 'Pets', 'version' => '1.0'],
        'paths' => [
            '/pets' => [
                'get' => [
                    'operationId' => 'listPets',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ]);

    $openApiFile = tempnam(sys_get_temp_dir(), 'openapi_').'.json';
    file_put_contents($openApiFile, $openApiJson);

    try {
        $sourceResolver = new DefaultSourceResolver(fetchers: ['file' => new LocalFetcher()]);
        $resolver = new OpenApiOperationResolver(
            new OpenApiDocumentLoader($sourceResolver),
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        $document = new ArazzoDocument(
            arazzo: '1.0.1',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [new SourceDescription('pets-api', $openApiFile, SourceType::Openapi)],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );

        $step = StepFactory::http(
            stepId: 'step1',
            description: null,
            flow: new StepFlow(),
            io: new StepIo(),
            operationId: '$sourceDescriptions.pets-api.listPets',
        );

        $handle = $resolver->resolve($step, $document);

        expect($handle->cebeOperation->operationId)->toBe('listPets')
            ->and($handle->openApi->openapi)->toBe('3.0.3')
            ->and($handle->operation->normalized->path)->toBe('/pets')
            // The seam exposes only the vendor-free projection, so
            // resolveModel() and resolve()->operation must agree.
            ->and($resolver->resolveModel($step, $document)->normalized->method)
            ->toBe($handle->operation->normalized->method);
    } finally {
        @unlink($openApiFile);
    }
});
