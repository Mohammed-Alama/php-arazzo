<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\CriteriaEvaluator;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\Registries\CriterionEvaluatorRegistry;
use Alama\Arazzo\Expression\ExpressionEngine;

function jsonPathContextStep(?string $context, string $condition): Step
{
    return StepFactory::http('x', null, new StepFlow(), new StepIo(successCriteria: [
        new SuccessCriterion($context, $condition, CriterionType::JsonPath),
    ]), operationId: 'op');
}

/** @param array<array-key, mixed> $body */
function jsonPathContextContext(array $body): WorkflowContext
{
    return (new WorkflowContext('w'))->withStepResponse('x', [
        'statusCode' => 200,
        'headers' => [],
        'body' => $body,
    ]);
}

it('applies the criterion to its resolved context, not the whole body', function (): void {
    // `$.ok` only exists under `/data`; the whole body would not match.
    $step = jsonPathContextStep('$response.body#/data', '$.ok');

    expect((new EvaluationEngine(expression: new ExpressionEngine()))
        ->evaluateSuccessCriteria($step, jsonPathContextContext(['data' => ['ok' => true]])))
        ->toBeTrue();
});

it('keeps applying the criterion to the body when no context is declared', function (): void {
    $step = jsonPathContextStep(null, '$.data.ok');

    expect((new EvaluationEngine(expression: new ExpressionEngine()))
        ->evaluateSuccessCriteria($step, jsonPathContextContext(['data' => ['ok' => true]])))
        ->toBeTrue();
});

it('fails deterministically when the context cannot be resolved', function (): void {
    // The body would pass `$.ok`; the criterion must not fall back to it.
    $step = jsonPathContextStep('$steps.missing.outputs.payload', '$.ok');

    expect((new EvaluationEngine(expression: new ExpressionEngine()))
        ->evaluateSuccessCriteria($step, jsonPathContextContext(['ok' => true])))
        ->toBeFalse();
});

it('honours the context on the registry-less built-in path too', function (): void {
    $step = jsonPathContextStep('$response.body#/data', '$.ok');
    $context = jsonPathContextContext(['data' => ['ok' => true]]);
    $expression = new ExpressionEngine();

    // Registry-less: the built-in evaluateJsonPath() branch. With a registry:
    // the JsonPathCriterionPlugin. Both must resolve the same root.
    expect((new CriteriaEvaluator($expression))->evaluateSuccessCriteria($step, $context))->toBeTrue()
        ->and((new CriteriaEvaluator($expression, null, new CriterionEvaluatorRegistry($expression)))
            ->evaluateSuccessCriteria($step, $context))->toBeTrue();
});
