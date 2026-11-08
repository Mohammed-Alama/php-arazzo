<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\StringInterpolator;
use Alama\Arazzo\Expression\Data\EmbeddedSegment;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Mockery;

function segments(array $pairs): array
{
    $out = [];
    $offset = 0;

    foreach ($pairs as [$isExpression, $text]) {
        $out[] = new EmbeddedSegment($isExpression, $text, $offset);
        $offset += strlen($text);
    }

    return $out;
}

function grammarInterpolator(): StringInterpolator
{
    return new StringInterpolator(new ExpressionEngine());
}

it('interpolates multiple expressions in a string', function () {
    $context = new WorkflowContext('wf1', ['token' => 'abc1234', 'userId' => 42]);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    $evaluator->shouldReceive('parseEmbedded')->andReturn(segments([
        [false, 'Bearer '],
        [true, '$inputs.token'],
        [false, ' for user '],
        [true, '$inputs.userId'],
    ]));
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
    $evaluator->shouldReceive('parseEmbedded')->andReturn(segments([
        [false, 'User data: '],
        [true, '$inputs.user'],
    ]));
    $evaluator->shouldReceive('evaluate')->andReturn(['id' => 42, 'name' => 'Alice']);

    $interpolator = new StringInterpolator($evaluator);

    $result = $interpolator->interpolate('User data: {$inputs.user}', $context, 'step1');

    expect($result)->toBe('User data: {"id":42,"name":"Alice"}');
});

it('leaves missing expressions blank', function () {
    $context = new WorkflowContext('wf1', []);
    $evaluator = Mockery::mock(ExpressionEngineInterface::class);
    $evaluator->shouldReceive('parseEmbedded')->andReturn(segments([
        [false, 'Bearer '],
        [true, '$inputs.missing'],
    ]));
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
    $evaluator->shouldReceive('parseEmbedded')->andReturnUsing(function ($raw) {
        return match ($raw) {
            'Bearer ${inputs.token}' => segments([
                [false, 'Bearer '],
                [true, '$inputs.token'],
            ]),
            'u-${inputs.userId}' => segments([
                [false, 'u-'],
                [true, '$inputs.userId'],
            ]),
            '${inputs.token}/${inputs.userId}' => segments([
                [true, '$inputs.token'],
                [false, '/'],
                [true, '$inputs.userId'],
            ]),
            default => [],
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
    $evaluator->shouldReceive('parseEmbedded')->andReturn(segments([
        [false, 'costs $5 and $token'],
    ]));
    $evaluator->shouldNotReceive('evaluate');

    $interpolator = new StringInterpolator($evaluator);

    expect($interpolator->interpolate('costs $5 and $token', $context, 'step1'))->toBe('costs $5 and $token');
});

it('returns a template without expressions untouched', function () {
    $context = new WorkflowContext('wf1', []);

    expect(grammarInterpolator()->interpolate('plain text', $context, 'step1'))->toBe('plain text');
});

it('interpolates adjacent expressions without inventing a separator', function () {
    $context = new WorkflowContext('wf1', ['a' => 'A', 'b' => 'B']);

    expect(grammarInterpolator()->interpolate('{$inputs.a}{$inputs.b}', $context, 'step1'))->toBe('AB');
});

it('passes a literal brace that does not open an expression through', function () {
    $context = new WorkflowContext('wf1', ['x' => 'X']);

    expect(grammarInterpolator()->interpolate('{not an expression} {$inputs.x}', $context, 'step1'))
        ->toBe('{not an expression} X');
});

it('closes an embedded expression at the first closing brace', function () {
    // A `}` inside a JSON Pointer is not a delimiter under any supported spec
    // version, so it ends the expression and the remainder stays literal.
    $context = new WorkflowContext('wf1', ['user' => ['city' => 'Berlin']]);

    expect(grammarInterpolator()->interpolate('{$inputs.user#/city}b}', $context, 'step1'))
        ->toBe('Berlinb}');
});

it('returns the template unchanged when an embedded expression is malformed', function () {
    // Runtime interpolation must stay lenient: the validator is the strict
    // path, while odd step data passes through verbatim.
    $context = new WorkflowContext('wf1', []);

    expect(grammarInterpolator()->interpolate('Hello {$inputs.name', $context, 'step1'))
        ->toBe('Hello {$inputs.name')
        ->and(grammarInterpolator()->interpolate('Bad {$inputs.}!', $context, 'step1'))
        ->toBe('Bad {$inputs.}!');
});
