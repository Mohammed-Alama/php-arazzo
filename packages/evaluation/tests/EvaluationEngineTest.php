<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\ExpressionEngine;

function engineInput(WorkflowContext $context, ?string $stepId = null): EvaluationContext
{
    return new EvaluationContext($context, $stepId);
}

it('exposes a single entry-point interface', function (): void {
    expect(new EvaluationEngine(expression: new ExpressionEngine()))->toBeInstanceOf(EvaluationEngineInterface::class);
});

it('resolves an input expression from the context', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = (new WorkflowContext('def_1'))->withInput('name', 'Ada');

    expect($engine->evaluate(new Expression('{$inputs.name}'), engineInput($context)))->toBe('Ada');
});

it('resolves the ${...} spelling of a runtime expression', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = (new WorkflowContext('def_1'))->withInput('name', 'Ada');

    expect($engine->evaluate(new Expression('${inputs.name}'), engineInput($context)))->toBe('Ada')
        ->and($engine->evaluate(new Expression('$inputs.name'), engineInput($context)))->toBe('Ada');
});

it('resolves ${...} against http metadata and step outputs', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 201, 'body' => ['status' => 'OK']])
        ->withStepOutput('s1', 'user', 'Ada');

    expect($engine->evaluate(new Expression('${statusCode}'), engineInput($context, 's1')))->toBe(201)
        ->and($engine->evaluate(new Expression('${response.body#/status}'), engineInput($context, 's1')))->toBe('OK')
        ->and($engine->evaluate(new Expression('${steps.s1.outputs.user}'), engineInput($context, 's1')))->toBe('Ada');
});

it('returns null for a missing input in the ${...} spelling', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = new WorkflowContext('def_1');

    expect($engine->evaluate(new Expression('${inputs.missing}'), engineInput($context)))->toBeNull();
});

it('resolves http metadata against the current step', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 201, 'headers' => ['X-Mode' => 'Live'], 'body' => ['status' => 'OK']]);

    expect($engine->evaluate(new Expression('{$statusCode}'), engineInput($context, 's1')))->toBe(201);
    expect($engine->evaluate(new Expression('{$response.body#/status}'), engineInput($context, 's1')))->toBe('OK');
});

it('returns null for a missing input', function (): void {
    $engine = new EvaluationEngine(expression: new ExpressionEngine());
    $context = new WorkflowContext('def_1');

    expect($engine->evaluate(new Expression('{$inputs.missing}'), engineInput($context)))->toBeNull();
});
