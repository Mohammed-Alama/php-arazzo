<?php

declare(strict_types=1);

namespace Tests\Feature;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Components;
use Alama\Arazzo\Dto\Info;
use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\StepExecutor;
use Alama\Arazzo\Runner\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

//  removed since Pest.php binds it for the Feature folder

it('executes a step with automatic idempotency key injection using Laravel bindings', function (): void {
    config(['arazzo.idempotency.enabled' => true]);

    $capturedRequest = null;
    $openApiMock = \Mockery::mock(\Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface::class);
    $openApiMock->shouldReceive('execute')->once()->andReturnUsing(function ($source, $op, $payload, $interceptor) use (&$capturedRequest) {
        $request = new Request('POST', 'https://api.example.com/charges', [], '{"amount":100}');
        if ($interceptor) {
            $request = $interceptor($request);
        }
        $capturedRequest = $request;
        return new Response(201, [], '{"status":"ok"}');
    });

    app()->instance(\Alama\Arazzo\Runner\Contracts\OpenApiExecutorInterface::class, $openApiMock);

    // Mock ExpressionResolver since it is still used by HttpStepExecutor to extract outputs/validate
    $resolver = \Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('extractOutputs')->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->andReturn(true);
    app()->instance(ExpressionResolverInterface::class, $resolver);

    $executor = app(StepExecutor::class);

    $step = new Step('charge-step', null, 'chargeOp', null, null, [], null, [], [], [], []);
    $context = new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
    $document = new ArazzoDocument('1.0.0', new Info('t', null, null, '1'), [new \Alama\Arazzo\Dto\SourceDescription('src', 'http://api.example.com', \Alama\Arazzo\Dto\Enum\SourceType::Openapi)], [], new Components([], [], [], []), []);

    $executor->execute($step, $context, $document);

    expect($capturedRequest)->not->toBeNull();
    expect($capturedRequest->getHeaderLine('Idempotency-Key'))->toMatch('/^[0-9a-f]{64}$/');
});
