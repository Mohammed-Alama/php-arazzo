<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\StringInterpolator;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Mockery;

it('interpolates multiple expressions in a string', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234', 'userId' => 42]);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
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

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('Bearer {$inputs.token} for user {$inputs.userId}', $context, 'step1');

    expect($result)->toBe('Bearer abc1234 for user 42');
});

it('json encodes complex values', function () {
    $context = new WorkflowContext('wf1', ['user' => ['id' => 42, 'name' => 'Alice']]);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(['id' => 42, 'name' => 'Alice']);

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('User data: {$inputs.user}', $context, 'step1');

    expect($result)->toBe('User data: {"id":42,"name":"Alice"}');
});

it('leaves missing expressions blank', function () {
    $context = new WorkflowContext('wf1', []);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    $evaluator->shouldReceive('evaluate')->andReturn(null);

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('Bearer {$inputs.missing}', $context, 'step1');

    expect($result)->toBe('Bearer ');
});

it('interpolates the ${...} spelling and mixes it with the {$...} spelling', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234', 'userId' => 42]);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    // Both spellings must reach the evaluator in the canonical {$...} form.
    $evaluator->shouldReceive('evaluate')->andReturnUsing(function ($expr) {
        expect($expr->raw)->toStartWith('{$')->not()->toContain('${');

        return match ($expr->raw) {
            '{$inputs.token}' => 'abc1234',
            '{$inputs.userId}' => 42,
            default => null,
        };
    });

    $interpolator = new StringInterpolator($evaluator);

    expect($interpolator->interpolate('Bearer ${inputs.token}', $context, 'step1'))->toBe('Bearer abc1234')
        ->and($interpolator->interpolate('u-${inputs.userId}', $context, 'step1'))->toBe('u-42')
        ->and($interpolator->interpolate('${inputs.token}/${inputs.userId}', $context, 'step1'))->toBe('abc1234/42');
});

it('leaves a bare dollar that is not a braced expression alone', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234']);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    $evaluator->shouldNotReceive('evaluate');

    $interpolator = new StringInterpolator($evaluator);

    expect($interpolator->interpolate('costs $5 and $token', $context, 'step1'))->toBe('costs $5 and $token');
});
