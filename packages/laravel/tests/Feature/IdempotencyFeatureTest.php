<?php

declare(strict_types=1);

namespace Tests\Feature;

use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Normalizer\NormalizedOpenApiOperation;
use Alama\Arazzo\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Normalizer\ResolvedOperation;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Enum\SourceType;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\SourceDescription;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

//  removed since Pest.php binds it for the Feature folder

it('executes a step with automatic idempotency key injection using Laravel bindings', function (): void {
    config(['arazzo.idempotency.enabled' => true]);

    $capturedRequest = null;
    $openApiMock = \Mockery::mock(OpenApiExecutorInterface::class);
    $openApiMock->shouldReceive('execute')->once()->andReturnUsing(function ($op, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');
        if ($interceptor) {
            $request = $interceptor($request);
        }
        $capturedRequest = $request;

        return new Response(201, [], '{"status":"ok"}');
    });

    app()->instance(OpenApiExecutorInterface::class, $openApiMock);

    $resolver = \Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);
    app()->instance(ExpressionResolverInterface::class, $resolver);

    $opResolver = \Mockery::mock(OpenApiOperationResolver::class);
    $opResolver->shouldReceive('resolve')->andReturn(
        new ResolvedOperation(
            new SourceDescription('src', 'http://api.example.com', SourceType::Openapi),
            new NormalizedOpenApiOperation('/charges', 'post', 'http://api.example.com', [], [], [], [], [], []),
            new OpenApi([]),
            [],
            new Operation([]),
        ),
    );
    app()->instance(OpenApiOperationResolver::class, $opResolver);

    $executor = app(StepExecutor::class);

    $step = new Step('charge-step', null, 'chargeOp', null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [new SourceDescription('src', 'http://api.example.com', SourceType::Openapi)], [], new Components([], [], [], []), []);

    $executor->execute($step, $context, $document);

    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
});
