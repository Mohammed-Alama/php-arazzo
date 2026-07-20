<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Dto\Enum\ParameterIn;
use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Parameter;
use Alama\LaravelArazzo\Dto\PayloadReplacement;
use Alama\LaravelArazzo\Dto\RequestBody;
use Alama\LaravelArazzo\Dto\SourceDescription;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\SuccessCriterion;
use Alama\LaravelArazzo\Execution\ArazzoExpressionResolver;
use Alama\LaravelArazzo\Execution\Exceptions\UnsupportedCriterionTypeException;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
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

    $this->makeResolver = function (): ArazzoExpressionResolver {
        $sourceResolver = new DefaultSourceResolver(
            fetchers: ['file' => new LocalFetcher()],
            parsers: [SourceType::Openapi->value => new OpenApiSourceParser()],
        );

        return new ArazzoExpressionResolver($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
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
    $resolver = ($this->makeResolver)();
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

    $request = $resolver->compileRequest($step, new WorkflowContext('def_1'), $document);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.test/users?dryRun=1');
});

it('compiles request body with schema cast replacement', function () {
    $resolver = ($this->makeResolver)();
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

    $request = $resolver->compileRequest($step, $context, $document);

    expect(json_decode((string) $request->getBody(), true))->toBe(['age' => 30]);
});

it('falls back to literal url without a document', function () {
    $resolver = ($this->makeResolver)();

    $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $request = $resolver->compileRequest($step, $context);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('http://api.example.com/users');
});

it('extracts output via runtime expression with schema cast', function () {
    $resolver = ($this->makeResolver)();
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

    $outputs = $resolver->extractOutputs($step, $context, $document);

    expect($outputs['userId'])->toBe(123);
});

it('extracts output via bare jsonpath', function () {
    $resolver = ($this->makeResolver)();

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

    $outputs = $resolver->extractOutputs($step, $context);

    expect($outputs['firstId'])->toBe(1);
});

it('evaluates success criteria simple regex jsonpath', function () {
    $resolver = ($this->makeResolver)();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '{$statusCode} == 200', CriterionType::Simple),
            new SuccessCriterion('{$statusCode}', '^20[0-1]$', CriterionType::Regex),
            new SuccessCriterion(null, '$.users[?(@.id==1)]', CriterionType::JsonPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step1', [])
        ->withStepResponse('step1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => ['users' => [['id' => 1], ['id' => 2]]],
        ]);

    expect($resolver->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('evaluates success criteria unsupported', function () {
    $resolver = ($this->makeResolver)();

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '/users/id', CriterionType::XPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', []);

    $resolver->evaluateSuccessCriteria($step, $context);
})->throws(UnsupportedCriterionTypeException::class);

it('evaluateCriteria evaluates an arbitrary criteria list against the current step response, independent of successCriteria', function () {
    $resolver = ($this->makeResolver)();

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
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 500,
        'headers' => [],
        'body' => [],
    ]);

    $criteria = [
        new SuccessCriterion('{$statusCode}', '^5\d\d$', CriterionType::Regex),
    ];

    expect($resolver->evaluateCriteria($criteria, $step, $context))->toBeTrue();

    $failCriteria = [
        new SuccessCriterion('{$statusCode}', '^2\d\d$', CriterionType::Regex),
    ];

    expect($resolver->evaluateCriteria($failCriteria, $step, $context))->toBeFalse();
});

it('evaluateCriteria returns true for an empty criteria list', function () {
    $resolver = ($this->makeResolver)();

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
        outputs: [],
    );

    $context = new WorkflowContext('def_1');

    expect($resolver->evaluateCriteria([], $step, $context))->toBeTrue();
});

it('validates a response against the OpenAPI schema', function (): void {
    // Setup a dummy OpenAPI operation with a schema
    $operation = new Operation([
        'responses' => [
            '200' => new Response([
                'description' => 'Success',
                'content' => [
                    'application/json' => [
                        'schema' => new Schema([
                            'type' => 'object',
                            'required' => ['id'],
                            'properties' => ['id' => ['type' => 'integer']],
                        ]),
                    ],
                ],
            ]),
        ],
    ]);
    
    // We need to subclass or mock ArazzoExpressionResolver to intercept findOperation since it's an internal OpenAPI lookup
    $resolver = new class(
        new \Alama\LaravelArazzo\Resolution\DefaultSourceResolver([], []),
        new \GuzzleHttp\Psr7\HttpFactory(),
        new \Alama\LaravelArazzo\Execution\ExpressionEvaluator()
    ) extends \Alama\LaravelArazzo\Execution\ArazzoExpressionResolver {
        public ?Operation $mockOperation = null;
        public function __construct($sourceResolver, $requestFactory, $evaluator) { 
            parent::__construct($sourceResolver, $requestFactory, $evaluator); 
        }
        protected function findOperation(\Alama\LaravelArazzo\Dto\Step $step, ?\Alama\LaravelArazzo\Dto\ArazzoDocument $document = null): ?Operation {
            return $this->mockOperation;
        }
    };
    $resolver->mockOperation = $operation;

    $step = new \Alama\LaravelArazzo\Dto\Step('test-step', null, 'operationId', null, null, [], null, [], [], [], []);

    // 1. Valid data -> no exception
    $resolver->validateResponseSchema($step, 200, 'application/json', ['id' => 123]);
    expect(true)->toBeTrue(); // If we reached here, no exception was thrown

    // 2. Invalid data -> throws SchemaValidationException
    try {
        $resolver->validateResponseSchema($step, 200, 'application/json', ['name' => 'wrong']);
        $this->fail('Expected SchemaValidationException');
    } catch (\Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException $e) {
        expect($e->stepId)->toBe('test-step')
            ->and($e->violations)->toHaveCount(1)
            ->and($e->getMessage())->toContain("missing required property 'id'");
    }
    
    // 3. Different status code -> no schema found -> no exception (ignores)
    $resolver->validateResponseSchema($step, 201, 'application/json', ['name' => 'wrong']);
    
    // 4. Different content type -> no schema found -> no exception (ignores)
    $resolver->validateResponseSchema($step, 200, 'application/xml', ['name' => 'wrong']);
});
