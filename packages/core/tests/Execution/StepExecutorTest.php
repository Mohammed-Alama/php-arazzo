<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Exceptions\SchemaValidationException;
use Alama\Arazzo\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Resolver\ResolvedOperation;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\State\WorkflowContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

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

function createMockOperationResolver(): OpenApiOperationResolver
{
    $mock = Mockery::mock(OpenApiOperationResolver::class);
    $mock->shouldReceive('resolve')->andReturn(new ResolvedOperation(
        new SourceDescription('test-src', 'http://example.com/openapi.json', SourceType::Openapi),
        new NormalizedOpenApiOperation('/rides', 'get', null, [], [], [], [], [], []),
        new OpenApi([]),
        [],
        new Operation([]),
    ));

    return $mock;
}

it('validates response schema if configured globally or locally', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('validateResponseSchema')->once()->andThrow(
        new SchemaValidationException('test-step', [['path' => '/', 'message' => 'bad schema']]),
    );
    $resolver->shouldReceive('extractOutputs')->never();
    $resolver->shouldReceive('evaluateSuccessCriteria')->never();

    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) {
        if ($interceptor) {
            $interceptor(new Request('GET', '/'));
        }

        return new Response(200, ['Content-Type' => 'application/json'], '{"bad": true}');
    });

    $executor = new StepExecutor($openApiExecutor, $resolver, createMockOperationResolver());
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, true);

    try {
        $executor->execute($step, new WorkflowContext('test-def'), createTestDocument());
        $this->fail('Expected SchemaValidationException');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('test-step');
    }
});

it('skips validation if configured off globally and locally', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('validateResponseSchema')->never();
    $resolver->shouldReceive('extractOutputs')->once()->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->once()->andReturn(true);

    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) {
        if ($interceptor) {
            $interceptor(new Request('GET', '/'));
        }

        return new Response(200, [], '{"bad": true}');
    });

    $executor = new StepExecutor($openApiExecutor, $resolver, createMockOperationResolver());
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, null);

    $result = $executor->execute($step, new WorkflowContext('test-def'), createTestDocument());
    expect($result[1])->toBeTrue();
});

it('injects the Idempotency-Key header into the request when the injector is enabled', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');
        if ($interceptor) {
            $capturedRequest = $interceptor($request);
        }

        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor(
        openApiExecutor: $openApiExecutor,
        expressionResolver: $resolver,
        operationResolver: createMockOperationResolver(),
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
    );
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);

    [$context] = $executor->execute($step, new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1'), createTestDocument());

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
    expect($context->getSteps()['test-step']['request']['headers']['Idempotency-Key'] ?? null)
        ->toMatch('/^[0-9a-f]{64}$/');
});

it('does not inject a header when no injector is passed', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/x', [], '{"a":1}');
        if ($interceptor) {
            $capturedRequest = $interceptor($request);
        }

        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor($openApiExecutor, $resolver, createMockOperationResolver());
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);

    $executor->execute($step, new WorkflowContext('def-1'), createTestDocument());

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toBe('');
});

it('does not inject a header on non-mutating verbs even when the injector is enabled', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);

    $capturedRequest = null;
    $openApiExecutor = Mockery::mock(OpenApiExecutorInterface::class);
    $openApiExecutor->shouldReceive('execute')->andReturnUsing(function ($resolved, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('GET', 'https://api.example.com/x');
        if ($interceptor) {
            $capturedRequest = $interceptor($request);
        }

        return new Response(200, [], '{}');
    });

    $executor = new StepExecutor(
        openApiExecutor: $openApiExecutor,
        expressionResolver: $resolver,
        operationResolver: createMockOperationResolver(),
        strictValidationDefault: false,
        injector: new IdempotencyKeyInjector(enabledDefault: true, headerDefault: 'Idempotency-Key'),
    );
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], []);

    $executor->execute($step, new WorkflowContext('def-1'), createTestDocument());

    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toBe('');
});
