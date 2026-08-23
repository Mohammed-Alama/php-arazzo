<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;

it('merges step response and outputs into context correctly', function () {
    $context = new WorkflowContext('wf1', []);

    // Simulate step 1 request and response
    $context = $context->withStepRequest('step1', ['method' => 'GET', 'url' => 'http://api.test']);
    $context = $context->withStepResponse('step1', ['statusCode' => 200, 'headers' => [], 'body' => ['id' => 42]]);
    $context = $context->withStepOutput('step1', 'userId', 42);

    $steps = $context->getSteps();
    expect($steps)->toHaveKey('step1')
        ->and($steps['step1']['request']['method'])->toBe('GET')
        ->and($steps['step1']['response']['statusCode'])->toBe(200)
        ->and($steps['step1']['response']['body'])->toBe(['id' => 42])
        ->and($steps['step1']['outputs']['userId'])->toBe(42);

    // Simulate step 2
    $context = $context->withStepResponse('step2', ['statusCode' => 201, 'body' => ['status' => 'created']]);
    $context = $context->withStepOutput('step2', 'status', 'created');

    $steps = $context->getSteps();
    expect($steps)->toHaveKey('step1')
        ->and($steps)->toHaveKey('step2')
        ->and($steps['step2']['outputs']['status'])->toBe('created');
});
