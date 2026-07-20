<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

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
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class ArazzoExpressionResolverTest extends TestCase
{
    private string $openApiFile;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function tearDown(): void
    {
        @unlink($this->openApiFile);
        parent::tearDown();
    }

    private function makeResolver(): ArazzoExpressionResolver
    {
        $sourceResolver = new DefaultSourceResolver(
            fetchers: ['file' => new LocalFetcher()],
            parsers: [SourceType::Openapi->value => new OpenApiSourceParser()],
        );

        return new ArazzoExpressionResolver($sourceResolver, new HttpFactory(), new ExpressionEvaluator());
    }

    private function makeDocument(): ArazzoDocument
    {
        return new ArazzoDocument(
            arazzo: '1.0.1',
            info: new Info('Test', null, null, '1.0.0'),
            sourceDescriptions: [new SourceDescription('test-api', $this->openApiFile, SourceType::Openapi)],
            workflows: [],
            components: new Components([], [], [], []),
            specificationExtensions: [],
        );
    }

    public function test_compiles_request_with_resolved_operation_and_cast_query_param(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

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

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.test/users?dryRun=1', (string) $request->getUri());
    }

    public function test_compiles_request_body_with_schema_cast_replacement(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

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

        $this->assertSame(['age' => 30], json_decode((string) $request->getBody(), true));
    }

    public function test_falls_back_to_literal_url_without_a_document(): void
    {
        $resolver = $this->makeResolver();

        $step = new Step('step1', null, null, 'http://api.example.com/users', null, [], null, [], [], [], []);
        $context = new WorkflowContext('def_1');

        $request = $resolver->compileRequest($step, $context);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://api.example.com/users', (string) $request->getUri());
    }

    public function test_extracts_output_via_runtime_expression_with_schema_cast(): void
    {
        $resolver = $this->makeResolver();
        $document = $this->makeDocument();

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

        $this->assertSame(123, $outputs['userId']);
    }

    public function test_extracts_output_via_bare_jsonpath(): void
    {
        $resolver = $this->makeResolver();

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

        $this->assertSame(1, $outputs['firstId']);
    }

    public function test_evaluates_success_criteria_simple_regex_jsonpath(): void
    {
        $resolver = $this->makeResolver();

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

        $this->assertTrue($resolver->evaluateSuccessCriteria($step, $context));
    }

    public function test_evaluates_success_criteria_unsupported(): void
    {
        $resolver = $this->makeResolver();

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

        $this->expectException(UnsupportedCriterionTypeException::class);
        $resolver->evaluateSuccessCriteria($step, $context);
    }
}
