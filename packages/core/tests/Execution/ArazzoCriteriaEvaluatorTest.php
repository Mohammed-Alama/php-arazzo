<?php

declare(strict_types=1);

namespace Tests\Execution;

use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Evaluation\ArazzoCriteriaEvaluator;
use Alama\Arazzo\Expression\ExpressionEvaluator;
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

it('evaluates xpath criteria against xml response bodies', function () {
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
            new SuccessCriterion(null, '/users/user[@id="1"]/name', CriterionType::XPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => '<?xml version="1.0"?><users><user id="1"><name>Amy</name></user></users>',
    ]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeTrue();

    $missingMatch = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => '<?xml version="1.0"?><users/>',
    ]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeTrue()
        ->and($evaluator->evaluateSuccessCriteria($step, $missingMatch))->toBeFalse();
});

it('fails xpath criteria deterministically on non-xml bodies', function () {
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
            new SuccessCriterion(null, '/users', CriterionType::XPath),
        ],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['not' => 'xml'],
    ]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

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

it('simple criteria fail the step when the status code does not match', function () {
    $evaluator = $this->evaluator;

    $makeStep = fn (string $condition) => new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, $condition, CriterionType::Simple)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $ok = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 200, 'headers' => [], 'body' => []]);
    $serverError = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 500, 'headers' => [], 'body' => []]);

    expect($evaluator->evaluateSuccessCriteria($makeStep('$statusCode == 200'), $ok))->toBeTrue();
    expect($evaluator->evaluateSuccessCriteria($makeStep('$statusCode == 200'), $serverError))->toBeFalse();
    expect($evaluator->evaluateSuccessCriteria($makeStep('{$statusCode} == 200 && $statusCode < 300'), $ok))->toBeTrue();
});

it('malformed simple criteria fail deterministically instead of throwing', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '$statusCode === 200', CriterionType::Simple)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 200, 'headers' => [], 'body' => []]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('jsonpath criteria evaluate against the declared context node', function () {
    $evaluator = $this->evaluator;

    $makeStep = fn (string $context) => new Step(
        stepId: 's2',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion($context, '$[?(@.ok == true)]', CriterionType::JsonPath)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => ['items' => [['ok' => true]]]])
        ->withStepOutput('s1', 'list', [['ok' => true]]);

    expect($evaluator->evaluateSuccessCriteria($makeStep('{$steps.s1.outputs.list}'), $context))->toBeTrue();

    $emptyContext = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => []])
        ->withStepOutput('s1', 'list', []);

    expect($evaluator->evaluateSuccessCriteria($makeStep('{$steps.s1.outputs.list}'), $emptyContext))->toBeFalse();
});

it('jsonpath criteria without context fall back to the current response body', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '$.users[*].id', CriterionType::JsonPath)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', [
        'statusCode' => 200,
        'headers' => [],
        'body' => ['users' => [['id' => 1], ['id' => 2]]],
    ]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeTrue();
});

it('regex criteria without a context fail instead of being skipped', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion(null, '^20[0-1]$', CriterionType::Regex)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 200, 'headers' => [], 'body' => []]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});

it('regex criteria against a missing context value fail deterministically', function () {
    $evaluator = $this->evaluator;

    $step = new Step(
        stepId: 'step1',
        description: null,
        operationId: null,
        operationPath: null,
        workflowId: null,
        parameters: [],
        requestBody: null,
        successCriteria: [new SuccessCriterion('{$response.header.X-Missing}', '^.*$', CriterionType::Regex)],
        onSuccess: [],
        onFailure: [],
        outputs: [],
    );

    $context = (new WorkflowContext('def_1'))->withStepResponse('step1', ['statusCode' => 200, 'headers' => [], 'body' => []]);

    expect($evaluator->evaluateSuccessCriteria($step, $context))->toBeFalse();
});
