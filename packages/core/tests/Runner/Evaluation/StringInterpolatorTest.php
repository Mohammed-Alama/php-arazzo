<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Evaluation;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Evaluation\StringInterpolator;
use Mockery;

it('interpolates multiple expressions in a string', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234', 'userId' => 42]);
    $evaluator = Mockery::mock(ExpressionResolverInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturnUsing(function ($expr, $ctx, $stepId) {
        $raw = $expr->raw;
        if ($raw === '{$inputs.token}') {
            return 'abc1234';
        }
        if ($raw === '{$inputs.userId}') {
            return 42;
        }

        return null;
    });

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('Bearer {$inputs.token} for user {$inputs.userId}', $context, 'step1');

    expect($result)->toBe('Bearer abc1234 for user 42');
});

it('json encodes complex values', function () {
    $context = new WorkflowContext('wf1', ['user' => ['id' => 42, 'name' => 'Alice']]);
    $evaluator = Mockery::mock(ExpressionResolverInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(['id' => 42, 'name' => 'Alice']);

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('User data: {$inputs.user}', $context, 'step1');

    expect($result)->toBe('User data: {"id":42,"name":"Alice"}');
});

it('leaves missing expressions blank', function () {
    $context = new WorkflowContext('wf1', []);
    $evaluator = Mockery::mock(ExpressionResolverInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(null);

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('Bearer {$inputs.missing}', $context, 'step1');

    expect($result)->toBe('Bearer ');
});
