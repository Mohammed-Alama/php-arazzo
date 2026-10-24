<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\Plugins\JsonPathExpressionPlugin;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\ExpressionEngine;

it('claims only jsonpath expressions', function (string $raw, bool $expected): void {
    $plugin = new JsonPathExpressionPlugin(new ExpressionEngine());

    expect($plugin->supports(new Expression($raw)))->toBe($expected);
})->with([
    // JSONPath roots: the sigil followed by a member/index step, or nothing.
    ['$', true],
    ['$.foo', true],
    ["$['a']", true],
    ['$[0]', true],
    ['$..id', true],
    ['$[?(@.price < 10)]', true],
    ['@', true],
    ['@.x', true],
    ['@[0]', true],

    // Arazzo runtime references: never JSONPath, whatever they look like.
    ['$outputs.workflow_order_id', false],
    ['$statusCode', false],
    ['$self', false],
    ['$url', false],
    ['$method', false],
    ['$message.payload', false],
    ['$inputs.user', false],
    ['$steps.s1.outputs.x', false],
    ['$response.body#/id', false],
    ['$request.header.X', false],
    ['$workflows.w.outputs.x', false],
    ['${steps.s1.outputs.x}', false],
]);

it('evaluates every Arazzo runtime reference exactly like the expression engine', function (string $raw): void {
    $context = (new WorkflowContext('def_1'))->withStepResult('s1', [
        'outputs' => ['workflow_order_id' => 'ord_9'],
        'response' => ['statusCode' => 200, 'headers' => [], 'body' => ['id' => 7]],
    ]);
    $input = new EvaluationContext($context, 's1');
    $expression = new ExpressionEngine();
    $facade = new EvaluationEngine(expression: $expression);

    expect($facade->evaluate(new Expression($raw), $input))
        ->toBe($expression->evaluate(new Expression($raw), $input));
})->with([
    '$outputs.workflow_order_id',
    '$statusCode',
    '$self',
    '$url',
    '$method',
    '$message.payload',
]);
