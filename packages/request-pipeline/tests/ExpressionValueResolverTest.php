<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\RequestPipeline;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Evaluation\Interfaces\EvaluationInputInterface;
use Alama\Arazzo\RequestPipeline\Data\ExecutionEvaluationInput;
use Alama\Arazzo\RequestPipeline\ExpressionValueResolver;

function valueResolverFor(EvaluationEngineInterface $engine): ExpressionValueResolver
{
    return new ExpressionValueResolver($engine);
}

function valueContext(): WorkflowContext
{
    return new WorkflowContext('def-1', [], [], [], 'wf-1', 'exec-1');
}

it('passes non-string, non-expression values straight through', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('evaluate');
    $engine->shouldNotReceive('evaluateSelector');
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve(42, valueContext()))->toBe(42)
        ->and(valueResolverFor($engine)->resolve(null, valueContext()))->toBeNull()
        ->and(valueResolverFor($engine)->resolve(['a'], valueContext()))->toBe(['a'])
        ->and(valueResolverFor($engine)->resolve(true, valueContext()))->toBeTrue();
});

it('returns a plain string with no runtime expression untouched', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve('plain text', valueContext()))->toBe('plain text');
});

it('interpolates a string carrying the {$...} template form', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContextInterface::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('{$inputs.name}', valueContext(), 'step-a'))->toBe('Ada');
});

it('normalises the bare $inputs.x spelling into the template form', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContextInterface::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('$inputs.name', valueContext(), 'step-a'))->toBe('Ada');
});

it('forwards the ${inputs.x} spelling to the interpolator', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('${inputs.name}', \Mockery::type(WorkflowContextInterface::class), 'step-a')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('${inputs.name}', valueContext(), 'step-a'))->toBe('Ada');
});

it('forwards an embedded ${inputs.x} spelling to the interpolator', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('user-${inputs.name}', \Mockery::type(WorkflowContextInterface::class), 'step-a')
        ->andReturn('user-Ada');

    expect(valueResolverFor($engine)->resolve('user-${inputs.name}', valueContext(), 'step-a'))->toBe('user-Ada');
});

it('resolves every Arazzo runtime-expression spelling end to end', function (): void {
    // Regression guard: the mocked tests above only prove the value is handed
    // over, so a real engine is needed to prove it is actually evaluated.
    $resolver = new ExpressionValueResolver(new EvaluationEngine());
    $context = new WorkflowContext('def-1', ['name' => 'Ada'], [], [], 'wf-1', 'exec-1');

    expect($resolver->resolve('{$inputs.name}', $context, 'step-a'))->toBe('Ada')
        ->and($resolver->resolve('${inputs.name}', $context, 'step-a'))->toBe('Ada')
        ->and($resolver->resolve('$inputs.name', $context, 'step-a'))->toBe('Ada')
        ->and($resolver->resolve('user-{$inputs.name}', $context, 'step-a'))->toBe('user-Ada')
        ->and($resolver->resolve('user-${inputs.name}', $context, 'step-a'))->toBe('user-Ada')
        ->and($resolver->resolve('${inputs.name}', $context))->toBe('Ada');
});

it('leaves a dollar string alone when the character after it is not a letter', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve('$5 and $6', valueContext(), 'step-a'))->toBe('$5 and $6');
});

it('leaves a bare dollar expression containing whitespace alone', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldNotReceive('interpolate');

    expect(valueResolverFor($engine)->resolve('$inputs.a and more', valueContext(), 'step-a'))
        ->toBe('$inputs.a and more');
});

it('routes a Selector to evaluateSelector with the step id', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $selector = new Selector('response', '$.id', ExpressionType::JsonPath);

    $engine->shouldReceive('evaluateSelector')
        ->once()
        ->with($selector, \Mockery::type(WorkflowContextInterface::class), 'step-a')
        ->andReturn('selected');

    expect(valueResolverFor($engine)->resolve($selector, valueContext(), 'step-a'))->toBe('selected');
});

it('routes an Expression to evaluate, carrying the step id in the evaluation input', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $expression = new Expression('$inputs.count');
    $seen = null;

    $engine->shouldReceive('evaluate')
        ->once()
        ->with($expression, \Mockery::type(EvaluationInputInterface::class))
        ->andReturnUsing(function (Expression $expression, EvaluationInputInterface $input) use (&$seen): int {
            $seen = $input;

            return 7;
        });

    $context = valueContext();

    expect(valueResolverFor($engine)->resolve($expression, $context, 'step-a'))->toBe(7)
        ->and($seen)->toBeInstanceOf(ExecutionEvaluationInput::class)
        ->and($seen->getCurrentStepId())->toBe('step-a')
        ->and($seen->getWorkflowContext())->toBe($context);
});

it('defaults the step id to an empty string when none is supplied', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $engine->shouldReceive('interpolate')
        ->once()
        ->with('{$inputs.name}', \Mockery::type(WorkflowContextInterface::class), '')
        ->andReturn('Ada');

    expect(valueResolverFor($engine)->resolve('{$inputs.name}', valueContext()))->toBe('Ada');
});

it('defaults the step id to an empty string for a Selector too', function (): void {
    $engine = \Mockery::mock(EvaluationEngineInterface::class);
    $selector = new Selector('response', '$.id', ExpressionType::JsonPath);

    $engine->shouldReceive('evaluateSelector')
        ->once()
        ->with($selector, \Mockery::type(WorkflowContextInterface::class), '')
        ->andReturn('selected');

    expect(valueResolverFor($engine)->resolve($selector, valueContext()))->toBe('selected');
});
