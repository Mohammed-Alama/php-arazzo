<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Expression\ExpressionEngine;

function valueContext(): WorkflowContext
{
    return new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
}

function valueEngine(): EvaluationEngine
{
    return new EvaluationEngine(expression: new ExpressionEngine());
}

it('passes non-string, non-expression values straight through', function (): void {
    expect(valueEngine()->resolveValue(42, valueContext()))->toBe(42)
        ->and(valueEngine()->resolveValue(null, valueContext()))->toBeNull()
        ->and(valueEngine()->resolveValue(['a'], valueContext()))->toBe(['a'])
        ->and(valueEngine()->resolveValue(true, valueContext()))->toBeTrue();
});

it('returns a plain string with no runtime expression untouched', function (): void {
    expect(valueEngine()->resolveValue('plain text', valueContext()))->toBe('plain text');
});

it('interpolates a string carrying the {$...} template form', function (): void {
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect(valueEngine()->resolveValue('{$inputs.name}', $context, 'step-a'))->toBe('Ada');
});

it('normalises the bare $inputs.x spelling into the template form', function (): void {
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect(valueEngine()->resolveValue('$inputs.name', $context, 'step-a'))->toBe('Ada');
});

it('forwards the ${inputs.x} spelling to the interpolator', function (): void {
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect(valueEngine()->resolveValue('${inputs.name}', $context, 'step-a'))->toBe('Ada');
});

it('forwards an embedded ${inputs.x} spelling to the interpolator', function (): void {
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect(valueEngine()->resolveValue('user-${inputs.name}', $context, 'step-a'))->toBe('user-Ada');
});

it('resolves every Arazzo runtime-expression spelling end to end', function (): void {
    $engine = valueEngine();
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect($engine->resolveValue('{$inputs.name}', $context, 'step-a'))->toBe('Ada')
        ->and($engine->resolveValue('${inputs.name}', $context, 'step-a'))->toBe('Ada')
        ->and($engine->resolveValue('$inputs.name', $context, 'step-a'))->toBe('Ada')
        ->and($engine->resolveValue('user-{$inputs.name}', $context, 'step-a'))->toBe('user-Ada')
        ->and($engine->resolveValue('user-${inputs.name}', $context, 'step-a'))->toBe('user-Ada')
        ->and($engine->resolveValue('${inputs.name}', $context))->toBe('Ada');
});

it('leaves a dollar string alone when the character after it is not a letter', function (): void {
    expect(valueEngine()->resolveValue('$5 and $6', valueContext(), 'step-a'))->toBe('$5 and $6');
});

it('leaves a bare dollar expression containing whitespace alone', function (): void {
    expect(valueEngine()->resolveValue('$inputs.a and more', valueContext(), 'step-a'))
        ->toBe('$inputs.a and more');
});

it('routes a Selector to the selector evaluator with the step id', function (): void {
    $context = new WorkflowContext(
        'def-1',
        [],
        ['step-a' => ['response' => ['body' => ['id' => 'selected']]]],
        [],
        'wf-1',
        'exec-1',
    );
    $selector = new Selector(null, '$.id', ExpressionType::JsonPath);

    expect(valueEngine()->resolveValue($selector, $context, 'step-a'))->toBe('selected');
});

it('routes an Expression to the expression evaluator with the step id', function (): void {
    $context = new WorkflowContext('def-1', ['count' => 7], [], [], 'wf-1', 'exec-1');
    $expression = new Expression('$inputs.count');

    expect(valueEngine()->resolveValue($expression, $context, 'step-a'))->toBe(7);
});

it('defaults the step id to an empty string when none is supplied', function (): void {
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect(valueEngine()->resolveValue('{$inputs.name}', $context))->toBe('Ada');
});

it('defaults the step id to an empty string for a Selector too', function (): void {
    $context = new WorkflowContext(
        'def-1',
        [],
        ['' => ['response' => ['body' => ['id' => 'selected']]]],
        [],
        'wf-1',
        'exec-1',
    );

    expect(valueEngine()->resolveValue(new Selector(null, '$.id', ExpressionType::JsonPath), $context))
        ->toBe('selected');
});
