<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolver;

use Alama\Arazzo\Exceptions\UnsupportedSourceVersionException;
use Alama\Arazzo\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Expression\Enum\SourceType;
use Alama\Arazzo\Expression\SourceDescription;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;

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

        $step = new Step(
            stepId: 'step1',
            description: null,
            operationId: 'legacy-api.listPets',
            operationPath: null,
            workflowId: null,
            parameters: [],
            requestBody: null,
            successCriteria: [],
            onSuccess: [],
            onFailure: [],
            outputs: [],
        );

        $resolver->resolve($step, $document);
    } finally {
        @unlink($swaggerFile);
    }
})->throws(UnsupportedSourceVersionException::class, 'declares version \'2.0\', which is not supported');
