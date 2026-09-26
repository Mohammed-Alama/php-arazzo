<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Interfaces\ExpressionEvaluatorInterface;
use Alama\Arazzo\Evaluation\InterpolationResolver;
use Alama\Arazzo\Evaluation\StringInterpolator;
use Mockery;

it('interpolates multiple expressions in a string', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234', 'userId' => 42]);
    $evaluator = Mockery::mock(ExpressionEvaluatorInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturnUsing(function ($expr, $ctx) {
        $raw = $expr->raw;
        if ($raw === '{$inputs.token}') {
            return 'abc1234';
        }
        if ($raw === '{$inputs.userId}') {
            return 42;
        }

        return null;
    });

    $resolver = new InterpolationResolver($evaluator);
    $interpolator = new StringInterpolator($resolver);

    $result = $interpolator->interpolate('Bearer {$inputs.token} for user {$inputs.userId}', $context, 'step1');

    expect($result)->toBe('Bearer abc1234 for user 42');
});

it('json encodes complex values', function () {
    $context = new WorkflowContext('wf1', ['user' => ['id' => 42, 'name' => 'Alice']]);
    $evaluator = Mockery::mock(ExpressionEvaluatorInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(['id' => 42, 'name' => 'Alice']);

    $resolver = new InterpolationResolver($evaluator);
    $interpolator = new StringInterpolator($resolver);

    $result = $interpolator->interpolate('User data: {$inputs.user}', $context, 'step1');

    expect($result)->toBe('User data: {"id":42,"name":"Alice"}');
});

it('leaves missing expressions blank', function () {
    $context = new WorkflowContext('wf1', []);
    $evaluator = Mockery::mock(ExpressionEvaluatorInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(null);

    $resolver = new InterpolationResolver($evaluator);
    $interpolator = new StringInterpolator($resolver);

    $result = $interpolator->interpolate('Bearer {$inputs.missing}', $context, 'step1');

    expect($result)->toBe('Bearer ');
});
