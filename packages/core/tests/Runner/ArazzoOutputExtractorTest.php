<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Execution\ArazzoOutputExtractor;
use Alama\Arazzo\Execution\OpenApiDocumentLoader;
use Alama\Arazzo\Normalizer\OpenApi30Normalizer;
use Alama\Arazzo\Normalizer\OpenApi31Normalizer;
use Alama\Arazzo\Normalizer\OpenApiVersionDetector;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\ExpressionType;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Selector;
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

    $this->openApiFile = tempnam(sys_get_temp_dir(), 'openapi_').'.json';
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

it('extracts output via json pointer selector', function () {
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
        outputs: ['firstUser' => new Selector(null, '/users/0/id', ExpressionType::JsonPointer)],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['users' => [['id' => 7], ['id' => 8]]],
    ]);

    $outputs = $extractor->extractOutputs($step, $context);

    expect($outputs['firstUser'])->toBe(7);
});

it('extracts output via selector with a context expression', function () {
    $extractor = ($this->makeExtractor)();

    $step = new Step(
        stepId: 's2',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: ['token' => new Selector('{$steps.s1.outputs.auth}', '$.access.token', ExpressionType::JsonPath)],
    );

    $context = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => []])
        ->withStepOutput('s1', 'auth', ['access' => ['token' => 'jwt-9']]);

    $outputs = $extractor->extractOutputs($step, $context);

    expect($outputs['token'])->toBe('jwt-9');
});
