<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Evaluation;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\ExpressionEvaluator;

function conditionContext(): WorkflowContext
{
    return (new WorkflowContext('def_1'))
        ->withStepRequest('s1', ['method' => 'POST', 'url' => 'http://x/y'])
        ->withStepResponse('s1', [
            'statusCode' => 201,
            'headers' => ['X-Mode' => 'Live'],
            'body' => ['status' => 'OK', 'count' => 3, 'ok' => true, 'nil' => null],
        ]);
}

function evalCondition(string $condition): mixed
{
    $evaluator = new ConditionEvaluator(new ExpressionEvaluator());

    return $evaluator->evaluate($condition, conditionContext(), 's1');
}

it('compares status code equality', function () {
    expect(evalCondition('$statusCode == 201'))->toBeTrue();
    expect(evalCondition('$statusCode == 200'))->toBeFalse();
});

it('compares inequality', function () {
    expect(evalCondition('$statusCode != 200'))->toBeTrue();
    expect(evalCondition('$statusCode != 201'))->toBeFalse();
});

it('performs ordered numeric comparisons', function () {
    expect(evalCondition('$statusCode > 200'))->toBeTrue();
    expect(evalCondition('$statusCode >= 201'))->toBeTrue();
    expect(evalCondition('$statusCode < 300'))->toBeTrue();
    expect(evalCondition('$statusCode <= 200'))->toBeFalse();
});

it('compares strings case-insensitively', function () {
    expect(evalCondition('$response.body#/status == "ok"'))->toBeTrue();
    expect(evalCondition('$response.body#/status == \'Ok\''))->toBeTrue();
    expect(evalCondition('$response.body#/status == \'error\''))->toBeFalse();
});

it('resolves response headers and request metadata', function () {
    expect(evalCondition('$response.header.X-Mode == "live"'))->toBeTrue();
    expect(evalCondition('$method == "post"'))->toBeTrue();
    expect(evalCondition('$url == "http://x/y"'))->toBeTrue();
});

it('supports boolean and null literals', function () {
    expect(evalCondition('$response.body#/ok == true'))->toBeTrue();
    expect(evalCondition('$response.body#/ok == false'))->toBeFalse();
    expect(evalCondition('$response.body#/nil == null'))->toBeTrue();
});

it('treats a missing expression value as not equal to a concrete literal', function () {
    expect(evalCondition('$response.body#/missing == "x"'))->toBeFalse();
});

it('combines conditions with && and || respecting precedence', function () {
    expect(evalCondition('$statusCode == 201 && $response.body#/count > 2'))->toBeTrue();
    expect(evalCondition('$statusCode == 500 || $response.body#/count > 2'))->toBeTrue();
    expect(evalCondition('$statusCode == 500 || $response.body#/count > 99 && $statusCode == 201'))->toBeFalse();
});

it('groups sub-expressions with parentheses', function () {
    expect(evalCondition('($statusCode == 500 || $statusCode == 201) && $response.body#/ok == true'))->toBeTrue();
});

it('negates with unary !', function () {
    expect(evalCondition('!($statusCode == 500)'))->toBeTrue();
    expect(evalCondition('!$response.body#/missing'))->toBeTrue();
    expect(evalCondition('!$response.body#/ok'))->toBeFalse();
});

it('evaluates a bare expression by truthiness', function () {
    expect(evalCondition('$response.body#/ok'))->toBeTrue();
    expect(evalCondition('$response.body#/missing'))->toBeFalse();
});

it('throws on malformed conditions', function (string $condition) {
    evalCondition($condition);
})->throws(ConditionSyntaxException::class)->with([
    '$statusCode ==',
    '== 200',
    '(($statusCode == 200)',
    '$statusCode === 200',
    '$statusCode &&',
    '',
]);

it('rejects unbalanced quotes', function () {
    evalCondition('$response.body#/status == \'ok');
})->throws(ConditionSyntaxException::class);
