<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Runner\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Runner\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Runner\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;

beforeEach(function () {
    $openApiJson = json_encode([
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test', 'version' => '1.0'],
        'servers' => [['url' => 'https://api.test']],
        'paths' => [
            '/users' => [
                'post' => [
                    'operationId' => 'createUser',
                    'responses' => [
                        '201' => [
                            'description' => 'Created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->openApiFile = tempnam(sys_get_temp_dir(), 'openapi_') . '.json';
    file_put_contents($this->openApiFile, $openApiJson);

    $this->makeExtractor = function (): ArazzoOutputExtractor {
        $sourceResolver = new DefaultSourceResolver(
            fetchers: ['file' => new LocalFetcher()],
        );
        $loader = new OpenApiDocumentLoader($sourceResolver);
        $resolver = new OpenApiOperationResolver(
            $loader,
            new OpenApiVersionDetector(),
            new OpenApi30Normalizer(),
            new OpenApi31Normalizer(),
        );

        return new ArazzoOutputExtractor($resolver, new ExpressionEvaluator());
    };

    $this->makeDocument = function (): ArazzoDocument {
        return new ArazzoDocument(
            arazzo: '1.0.1',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [new SourceDescription('test-api', $this->openApiFile, SourceType::Openapi)],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );
    };
});

afterEach(function () {
    @unlink($this->openApiFile);
});

it('extracts output via runtime expression with schema cast', function () {
    $extractor = ($this->makeExtractor)();
    $document = ($this->makeDocument)();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['userId' => new Expression('{$steps.create-user.response.body#/id}')],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('create-user', [
        'statusCode' => 201,
        'headers' => [],
        'body' => ['id' => '123'],
    ]);

    $outputs = $extractor->extractOutputs($step, $context, $document);

    expect($outputs['userId'])->toBe(123);
});

it('extracts output via bare jsonpath', function () {
    $extractor = ($this->makeExtractor)();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['firstId' => new Expression('$.users[0].id')],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['users' => [['id' => 1], ['id' => 2]]],
    ]);

    $outputs = $extractor->extractOutputs($step, $context);

    expect($outputs['firstId'])->toBe(1);
});
