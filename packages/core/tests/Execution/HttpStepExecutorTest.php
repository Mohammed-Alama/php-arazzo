<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Exceptions\SchemaValidationException;
use Alama\Arazzo\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\ResolvedOperation;
use Alama\Arazzo\Protocol\HttpStepExecutor;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\OpenApiPayload;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class HttpStepExecutorMockResolver implements ExpressionResolverInterface
{
    public ?WorkflowContext $lastContextSeenByExtractOutputs = null;

    public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
    {
        return $expression->raw;
    }

    public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

    /** @return array<string, mixed> */
    public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
    {
        $this->lastContextSeenByExtractOutputs = $context;

        return ['echoedBody' => $context->getSteps()[$step->stepId]['response']['body'] ?? null];
    }

    public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }

    public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
    {
        return true;
    }
}

class HttpStepExecutorMockOpenApiExecutor implements OpenApiExecutorInterface
{
    public function __construct(private ResponseInterface $response) {}

    public function execute(
        ResolvedOperation $resolvedOperation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface {
        if ($requestInterceptor) {
            $requestInterceptor(new Request('GET', 'http://localhost/thing'));
        }

        return $this->response;
    }
}

function createMockOperationResolver(): OpenApiOperationResolver
{
    $mock = \Mockery::mock(OpenApiOperationResolver::class);
    $mock->shouldReceive('resolve')->andReturn(new ResolvedOperation(
        new SourceDescription('test-src', 'http://example.com/openapi.json', SourceType::Openapi),
        new NormalizedOpenApiOperation('/rides', 'get', null, [], [], [], [], [], []),
        new OpenApi([]),
        [],
        new Operation([]),
    ));

    return $mock;
}

function createTestDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('Test', null, 'desc', '1.0.0'),
        sourceDescriptions: [new SourceDescription('test-src', 'http://example.com/openapi.json', SourceType::Openapi)],
        workflows: [],
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

function httpStepExecutorDocument(): ArazzoDocument
{
    return new ArazzoDocument(
        '1.0.0',
        new Info('T', null, null, '1'),
        [new SourceDescription('test', 'test.json', SourceType::Openapi)],
        [],
        new Components([], [], [], []),
        [],
    );
}

it('supports a step with no action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockOpenApiExecutor(new Response(200)), new HttpStepExecutorMockResolver(), createMockOperationResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeTrue();
});

it('does not support a step with an action set', function (): void {
    $executor = new HttpStepExecutor(new HttpStepExecutorMockOpenApiExecutor(new Response(200)), new HttpStepExecutorMockResolver(), createMockOperationResolver());
    $step = new Step('s1', null, null, null, null, [], null, [], [], [], [], [], 'send');

    expect($executor->supports($step, httpStepExecutorDocument()))->toBeFalse();
});

it('executes the request and returns a resolved outcome with statusCode/outputs/body', function (): void {
    $response = new Response(201, [], json_encode(['id' => 42]));
    $openApiExecutor = new HttpStepExecutorMockOpenApiExecutor($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($openApiExecutor, $resolver, createMockOperationResolver());

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1', [], [], [], 'wf_1', 'exec_1');

    $outcome = $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($outcome->suspended)->toBeFalse();
    expect($outcome->statusCode)->toBe(201);
    expect($outcome->responseBody)->toBe(['id' => 42]);
    expect($outcome->outputs)->toBe(['echoedBody' => ['id' => 42]]);
});

it('stores the response on the context before calling extractOutputs, fixing the stale-context ordering bug', function (): void {
    $response = new Response(200, [], json_encode(['x' => 1]));
    $openApiExecutor = new HttpStepExecutorMockOpenApiExecutor($response);
    $resolver = new HttpStepExecutorMockResolver();
    $executor = new HttpStepExecutor($openApiExecutor, $resolver, createMockOperationResolver());

    $step = new Step('s1', null, null, null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def_1');

    $executor->execute($step, $context, httpStepExecutorDocument(), 'exec_1');

    expect($resolver->lastContextSeenByExtractOutputs->getSteps()['s1']['response']['body'])->toBe(['x' => 1]);
});

it('validates response schema and fails fast on failure', function (): void {
    $resolver = \Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('validateResponseSchema')->once()->andThrow(
        new SchemaValidationException('sync-step', [['path' => '/', 'message' => 'bad schema']]),
    );
    $resolver->shouldReceive('extractOutputs')->never();

    $openApiExecutor = \Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) {
        if ($interceptor) {
            $interceptor(new Request('GET', '/'));
        }

        return new Response(200, [], '{"bad": true}');
    });

    $executor = new HttpStepExecutor($openApiExecutor, $resolver, createMockOperationResolver(), true); // strict default
    $step = new Step(
        stepId: 'sync-step',
        description: null,
        operationId: 'op',
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
        strictValidation: true,
    );

    $document = httpStepExecutorDocument();

    try {
        $executor->execute($step, new WorkflowContext('wf_1'), $document, 'exec_1');
        $this->fail('Expected exception');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('sync-step');
    }
});
