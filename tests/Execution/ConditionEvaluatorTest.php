<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Execution;

use Alama\LaravelArazzo\Execution\ConditionEvaluator;
use Alama\LaravelArazzo\Execution\ExpressionEvaluator;
use Alama\LaravelArazzo\Execution\VariableContext;

it('evaluates equality condition', function () {
    $context = new VariableContext();
    $context->setStepResponse('step1', ['statusCode' => 200]);
    $evaluator = new ExpressionEvaluator();
    $conditionEvaluator = new ConditionEvaluator($evaluator);

    expect($conditionEvaluator->evaluate('$statusCode == 200', 'step1', $context))->toBeTrue();
    expect($conditionEvaluator->evaluate('{$steps.step1.response.statusCode} == 200', 'step1', $context))->toBeTrue();
    expect($conditionEvaluator->evaluate('$statusCode == 404', 'step1', $context))->toBeFalse();
});

it('evaluates string equality condition', function () {
    $context = new VariableContext();
    $context->setStepResponse('step1', [
        'body' => ['status' => 'success'],
    ]);
    $evaluator = new ExpressionEvaluator();
    $conditionEvaluator = new ConditionEvaluator($evaluator);

    expect($conditionEvaluator->evaluate('$response.body#/status == \'success\'', 'step1', $context))->toBeTrue();
    expect($conditionEvaluator->evaluate('$response.body#/status == "success"', 'step1', $context))->toBeTrue();
    expect($conditionEvaluator->evaluate('$response.body#/status == pending', 'step1', $context))->toBeFalse();
});

it('evaluates matches condition', function () {
    $context = new VariableContext();
    $context->setStepResponse('step1', [
        'body' => ['id' => 'usr_123xyz'],
    ]);
    $evaluator = new ExpressionEvaluator();
    $conditionEvaluator = new ConditionEvaluator($evaluator);

    expect($conditionEvaluator->evaluate('$response.body#/id matches ^usr_', 'step1', $context))->toBeTrue();
    expect($conditionEvaluator->evaluate('$response.body#/id matches ^org_', 'step1', $context))->toBeFalse();
});
