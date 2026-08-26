<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Execution;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\OpenApiExecutorInterface;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Runner\Resolver\OpenApiOperationResolver;
use Alama\Arazzo\Runner\Resolver\ResolvedOperation;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Components;
use Alama\Arazzo\Spec\Info;
use Alama\Arazzo\Spec\Step;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('executes a step successfully and evaluates success criteria', function () {
    $doc = new ArazzoDocument('1.0.1', new Info('T', null, null, '1.0'), [], [], new Components([], [], [], []), []);
    $step = new Step('step1', null, 'test-op', null, null, [], null, [], [], [], [], []);
    $context = new WorkflowContext('wf1', []);

    $openApiExecutor = \Mockery::mock(OpenApiExecutorInterface::class);

    $responseStream = \Mockery::mock(StreamInterface::class);
    $responseStream->shouldReceive('__toString')->andReturn('{"id": 42}');

    $response = \Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getHeaders')->andReturn(['Content-Type' => ['application/json']]);
    $response->shouldReceive('getBody')->andReturn($responseStream);
    $response->shouldReceive('getHeaderLine')->with('Content-Type')->andReturn('application/json');

    $openApiExecutor->shouldReceive('execute')->once()->andReturn($response);

    $expressionResolver = \Mockery::mock(ExpressionResolverInterface::class);
    $expressionResolver->shouldReceive('extractOutputs')->once()->andReturn(['userId' => 42]);
    $expressionResolver->shouldReceive('evaluateSuccessCriteria')->once()->andReturn(true);

    $operationResolver = \Mockery::mock(OpenApiOperationResolver::class);
    $resolvedOp = \Mockery::mock(ResolvedOperation::class);
    $operationResolver->shouldReceive('resolve')->once()->andReturn($resolvedOp);

    $executor = new StepExecutor($openApiExecutor, $expressionResolver, $operationResolver);

    [$newContext, $success] = $executor->execute($step, $context, $doc);

    expect($success)->toBeTrue()
        ->and($newContext->getSteps()['step1']['response']['statusCode'])->toBe(200)
        ->and($newContext->getSteps()['step1']['outputs']['userId'])->toBe(42);
});
