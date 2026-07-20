<?php

declare(strict_types=1);

use Alama\LaravelArazzo\Execution\Exceptions\SchemaValidationException;

it('validates response schema if configured globally or locally', function (): void {
    // 1. Mock the ExpressionResolver to throw SchemaValidationException when validateResponseSchema is called
    $resolver = \Mockery::mock(\Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new \GuzzleHttp\Psr7\Request('GET', '/'));
    $resolver->shouldReceive('validateResponseSchema')->once()->andThrow(
        new SchemaValidationException('test-step', [['path' => '/', 'message' => 'bad schema']])
    );
    // extractOutputs and evaluateSuccessCriteria shouldn't be reached
    $resolver->shouldReceive('extractOutputs')->never();
    $resolver->shouldReceive('evaluateSuccessCriteria')->never();

    $client = \Mockery::mock(\Psr\Http\Client\ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturn(new \GuzzleHttp\Psr7\Response(200, [], '{"bad": true}'));

    $executor = new \Alama\LaravelArazzo\Execution\StepExecutor($client, $resolver);
    $step = new \Alama\LaravelArazzo\Dto\Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, true);
    
    $document = new \Alama\LaravelArazzo\Dto\ArazzoDocument(
        '1.0.0',
        new \Alama\LaravelArazzo\Dto\Info('test', null, null, '1.0'),
        [],
        [],
        new \Alama\LaravelArazzo\Dto\Components([], [], [], []),
        []
    );

    try {
        $executor->execute($step, new \Alama\LaravelArazzo\Execution\WorkflowContext('test-def'), $document);
        $this->fail('Expected SchemaValidationException');
    } catch (SchemaValidationException $e) {
        expect($e->stepId)->toBe('test-step');
    }
});

it('skips validation if configured off globally and locally', function (): void {
    $resolver = \Mockery::mock(\Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface::class);
    $resolver->shouldReceive('compileRequest')->andReturn(new \GuzzleHttp\Psr7\Request('GET', '/'));
    // Should NOT call validateResponseSchema
    $resolver->shouldReceive('validateResponseSchema')->never();
    $resolver->shouldReceive('extractOutputs')->once()->andReturn([]);
    $resolver->shouldReceive('evaluateSuccessCriteria')->once()->andReturn(true);

    $client = \Mockery::mock(\Psr\Http\Client\ClientInterface::class);
    $client->shouldReceive('sendRequest')->andReturn(new \GuzzleHttp\Psr7\Response(200, [], '{"bad": true}'));

    $executor = new \Alama\LaravelArazzo\Execution\StepExecutor($client, $resolver);
    $step = new \Alama\LaravelArazzo\Dto\Step('test-step', null, 'op', null, null, [], null, [], [], [], [], [], null, null, null, null);

    $document = new \Alama\LaravelArazzo\Dto\ArazzoDocument(
        '1.0.0',
        new \Alama\LaravelArazzo\Dto\Info('test', null, null, '1.0'),
        [],
        [],
        new \Alama\LaravelArazzo\Dto\Components([], [], [], []),
        []
    );

    $result = $executor->execute($step, new \Alama\LaravelArazzo\Execution\WorkflowContext('test-def'), $document);
    expect($result[1])->toBeTrue();
});
