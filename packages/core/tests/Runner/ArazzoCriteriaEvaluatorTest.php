<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Runner\Evaluation\ExpressionEvaluator;
use Alama\Arazzo\Runner\Exceptions\UnsupportedCriterionTypeException;
use Alama\Arazzo\Spec\Enum\CriterionType;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\SuccessCriterion;

beforeEach(function () {
    $this->evaluator = new ArazzoCriteriaEvaluator(new ExpressionEvaluator());
});

it('evaluates success criteria simple regex jsonpath', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '{$statusCode} == 200', CriterionType::Simple),
            new SuccessCriterion('{$statusCode}', '^20[0-1]$', CriterionType::Regex),
            new SuccessCriterion(null, '$.users[?(@.id==1)]', CriterionType::JsonPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))
        ->withStepRequest('step1', [])
        ->withStepResponse('step1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => ['users' => [['id' => 1], ['id' => 2]]],
        ]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('evaluates success criteria unsupported', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [
            new SuccessCriterion(null, '/users/id', CriterionType::XPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', []);

    $evaluator->evaluateSuccessCriteria($step, $context);
})->throws(UnsupportedCriterionTypeException::class);

it('evaluateCriteria evaluates an arbitrary criteria list against the current step response, independent of successCriteria', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 500,
        'headers' => [],
        'body' => [],
    ]);

    $criteria = [
        new SuccessCriterion('{$statusCode}', '^5\d\d$', CriterionType::Regex),
    ];

    expect($evaluator->evaluateCriteria($criteria, $step, $context))->toBeTrue();

    $failCriteria = [
        new SuccessCriterion('{$statusCode}', '^2\d\d$', CriterionType::Regex),
    ];

    expect($evaluator->evaluateCriteria($failCriteria, $step, $context))->toBeFalse();
});

it('evaluateCriteria returns true for an empty criteria list', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = new WorkflowContext('def_1');

    expect($evaluator->evaluateCriteria([], $step, $context))->toBeTrue();
});
