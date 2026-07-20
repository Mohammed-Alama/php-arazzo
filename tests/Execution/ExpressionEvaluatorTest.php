<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Execution;

use Alama\LaravelArazzo\Dto\Expression;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\VariableContext;

it('evaluates input references', function () {
    $context = new VariableContext(['userId' => 123]);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$inputs.userId}');
    expect($evaluator->evaluate($expr, $context))->toBe(123);

    $exprNotFound = new Expression('{$inputs.missing}');
    expect($evaluator->evaluate($exprNotFound, $context))->toBeNull();
});

it('evaluates step output references', function () {
    $context = new VariableContext();
    $context->setStepOutput('create-user', 'id', 456);
    $evaluator = new ExpressionEvaluator();

    $expr = new Expression('{$steps.create-user.outputs.id}');
    expect($evaluator->evaluate($expr, $context))->toBe(456);
});

it('evaluates request parts using json pointer', function () {
    $context = new VariableContext();
    $context->setStepRequest('step1', [
        'headers' => ['Authorization' => 'Bearer token'],
        'query' => ['search' => 'test'],
        'path' => ['id' => 789],
        'body' => ['user' => ['name' => 'Alice']],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.request.header.Authorization}'), $context))->toBe('Bearer token');
    expect($evaluator->evaluate(new Expression('{$steps.step1.request.body#/user/name}'), $context))->toBe('Alice');
});

it('evaluates response parts using json pointer', function () {
    $context = new VariableContext();
    $context->setStepResponse('step1', [
        'statusCode' => 201,
        'headers' => ['X-RateLimit' => '100'],
        'body' => ['data' => ['items' => [1, 2, 3]]],
    ]);

    $evaluator = new ExpressionEvaluator();

    expect($evaluator->evaluate(new Expression('{$steps.step1.response.statusCode}'), $context))->toBe(201);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.header.X-RateLimit}'), $context))->toBe('100');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/data/items/1}'), $context))->toBe(2);
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/missing}'), $context))->toBeNull();
});

it('evaluates json pointer with escaped characters', function () {
    $context = new VariableContext();
    $context->setStepResponse('step1', [
        'body' => [
            'foo~bar' => 'tilde',
            'foo/bar' => 'slash',
        ],
    ]);

    $evaluator = new ExpressionEvaluator();

    // RFC 6901: ~0 becomes ~, ~1 becomes /
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~0bar}'), $context))->toBe('tilde');
    expect($evaluator->evaluate(new Expression('{$steps.step1.response.body#/foo~1bar}'), $context))->toBe('slash');
});
