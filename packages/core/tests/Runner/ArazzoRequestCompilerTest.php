<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Enum\ParameterIn;
use Alama\Arazzo\Dto\Enum\SourceType;
use Alama\Arazzo\Dto\Expression;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Parameter;
use Alama\Arazzo\Dto\PayloadReplacement;
use Alama\Arazzo\Dto\RequestBody;
use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\Fetchers\LocalFetcher;
use Alama\Arazzo\Resolver\Parsers\OpenApiSourceParser;
use Alama\Arazzo\Runner\ArazzoRequestCompiler;
use Alama\Arazzo\Runner\ExpressionEvaluator;
use Alama\Arazzo\Runner\WorkflowContext;
use GuzzleHttp\Psr7\HttpFactory;

beforeEach(function () {
    $openApiJson = json_encode([
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test', 'version' => '1.0'],
        'servers' => [['url' => 'https://api.test']],
        'paths' => [
            '/users' => [
                'post' => [
                    'operationId' => 'createUser',
                    'parameters' => [
                        ['name' => 'dryRun', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'age' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
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

    $this->makeCompiler = function (): ArazzoRequestCompiler {
        $sourceResolver = new DefaultSourceResolver(
            fetchers: ['file' => new LocalFetcher()],
            parsers: [SourceType::Openapi->value => new OpenApiSourceParser()],
        );

        return new ArazzoRequestCompiler($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
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

it('compiles request with resolved operation and cast query param', function () {
    $compiler = ($this->makeCompiler)();
    $document = ($this->makeDocument)();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [new Parameter('dryRun', ParameterIn::Query, 'true')],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $request = $compiler->compileRequest($step, new WorkflowContext('def_1'), $document);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.test/users?dryRun=1');
});

it('compiles request body with schema cast replacement', function () {
    $compiler = ($this->makeCompiler)();
    $document = ($this->makeDocument)();

    $step = new Step(
        stepId: 'create-user',
        description: null,
        operationId: 'createUser',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: new RequestBody('application/json', ['age' => null], [
            new PayloadReplacement('/age', new Expression('{$inputs.age}')),
        ]),
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = new WorkflowContext('def_1', ['age' => '30']);

    $request = $compiler->compileRequest($step, $context, $document);

    expect(json_decode((string) $request->getBody(), true))->toBe(['age' => 30]);
});

it('falls back to literal url without a document', function () {
    $compiler = ($this->makeCompiler)();

    $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $request = $compiler->compileRequest($step, $context);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('http://api.example.com/users');
});
