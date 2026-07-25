<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Components;
use Alama\LaravelArazzo\Dto\Info;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException;
use Alama\LaravelArazzo\Execution\StepExecutor;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

it('validates response schema if configured globally or locally', function (): void {
    // 1. Mock the ExpressionResolver to throw SchemaValidationException when validateResponseSchema is called
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new Request('GET', '/'));
    $resolver->shouldReceive('validateResponseSchema')->once()->andThrow(
        new SchemaValidationException('test-step', [['path' => '/', 'message' => 'bad schema']]),
    );
    // extractOutputs and evaluateSuccessCriteria shouldn't be reached
    $resolver->shouldReceive('extractOutputs')->never();
    $resolver->shouldReceive('evaluateSuccessCriteria')->never();

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturn(new Response(200, [], '{"bad": true}'));

    $executor = new StepExecutor($client, $resolver);
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, true);

    $document = new ArazzoDocument(
        '1.0.0',
        new Info('test', null, null, '1.0'),
        [],
        [],
        new Components([], [], [], []),
        [],
    );

    try {
        $executor->execute($step, new WorkflowContext('test-def'), $document);
        $this->fail('Expected SchemaValidationException');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('test-step');
    }
});

it('skips validation if configured off globally and locally', function (): void {
    $resolver = Mockery::mock(ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new Request('GET', '/'));
    // Should NOT call validateResponseSchema
    $resolver->shouldReceive('validateResponseSchema')->never();
    $resolver->shouldReceive('extractOutputs')->once()->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->once()->andReturn(true);

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturn(new Response(200, [], '{"bad": true}'));

    $executor = new StepExecutor($client, $resolver);
    $step = new Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, null);

    $document = new ArazzoDocument(
        '1.0.0',
        new Info('test', null, null, '1.0'),
        [],
        [],
        new Components([], [], [], []),
        [],
    );

    $result = $executor->execute($step, new WorkflowContext('test-def'), $document);
    expect($result[1])->toBeTrue();
});
