<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Enum\CriterionOutcome;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\Plugins\JsonPathExpressionPlugin;
use Alama\Arazzo\Expression\ExpressionEngine;

/**
 * A JSONPath plugin receives whatever the context holds, so a response body may
 * be plain text. The registry hands that straight to the engine's `jsonPath()`,
 * which accepts only an array or an object.
 */
it('answers a jsonpath criterion against a response body that is not structured', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $criterion = new SuccessCriterion(null, '$.id', CriterionType::JsonPath);
    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());

    $structured = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => ['id' => 7]]);

    $text = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 200, 'headers' => [], 'body' => 'plain text']);

    $structuredResult = $engine->evaluateCriteria([$criterion], $step, $structured)->results[0];
    $textResult = $engine->evaluateCriteria([$criterion], $step, $text)->results[0];

    expect($structuredResult->outcome)->toBe(CriterionOutcome::Passed)
        ->and($textResult->outcome)->toBe(CriterionOutcome::Failed);
});

it('reports no matches when a jsonpath expression is evaluated over a scalar', function (): void {
    $plugin = new JsonPathExpressionPlugin(new ExpressionEngine());

    expect($plugin->evaluate(new Expression('$.id'), 'plain text'))->toBe([])
        ->and($plugin->evaluate(new Expression('$.id'), ['id' => 7]))->toBe(7);
});
