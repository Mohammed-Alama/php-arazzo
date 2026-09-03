<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Data\EvaluationInput;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\ExpressionEngineInterface;

function engineInput(WorkflowContext $context, ?string $stepId = null): EvaluationInput
{
    return new EvaluationInput($context, $stepId);
}

it('exposes a single entry-point interface', function (): void {
    expect(new ExpressionEngine())->toBeInstanceOf(ExpressionEngineInterface::class);
});

it('resolves an input expression from the context', function (): void {
    $engine = new ExpressionEngine();
    $context = (new WorkflowContext('def_1'))->withInput('name', 'Ada');

    expect($engine->evaluate(new Expression('{$inputs.name}'), engineInput($context)))->toBe('Ada');
});

it('resolves http metadata against the current step', function (): void {
    $engine = new ExpressionEngine();
    $context = (new WorkflowContext('def_1'))
        ->withStepResponse('s1', ['statusCode' => 201, 'headers' => ['X-Mode' => 'Live'], 'body' => ['status' => 'OK']]);

    expect($engine->evaluate(new Expression('{$statusCode}'), engineInput($context, 's1')))->toBe(201);
    expect($engine->evaluate(new Expression('{$response.body#/status}'), engineInput($context, 's1')))->toBe('OK');
});

it('returns null for a missing input', function (): void {
    $engine = new ExpressionEngine();
    $context = new WorkflowContext('def_1');

    expect($engine->evaluate(new Expression('{$inputs.missing}'), engineInput($context)))->toBeNull();
});
