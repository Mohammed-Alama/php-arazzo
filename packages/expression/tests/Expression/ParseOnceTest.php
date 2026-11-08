<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\Data\EvaluationContext;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionDependencyProvider;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Expression\Interfaces\ParserInterface;
use Alama\Arazzo\Expression\JsonPointer;
use Alama\Arazzo\Expression\Parser;
use Alama\Arazzo\Expression\Support\AstCache;

final class CountingExpressionParser implements ParserInterface
{
    public int $parses = 0;

    public function __construct(private readonly ParserInterface $inner) {}

    public function parse(string $raw): ExpressionAst
    {
        $this->parses++;

        return $this->inner->parse($raw);
    }
}

function countingExpressionParser(): CountingExpressionParser
{
    return new CountingExpressionParser(new Parser());
}

it('parses an expression once across repeated evaluations', function (): void {
    $parser = countingExpressionParser();
    $evaluator = new ExpressionEvaluator(new JsonPointer(), parser: $parser);
    $context = new WorkflowContext('def_1', ['name' => 'Ada']);

    expect($evaluator->evaluate(new Expression('{$inputs.name}'), new EvaluationContext($context)))->toBe('Ada');
    expect($evaluator->evaluate(new Expression('{$inputs.name}'), new EvaluationContext($context)))->toBe('Ada');
    expect($evaluator->evaluate(new Expression('{$inputs.name}'), new EvaluationContext($context)))->toBe('Ada');

    expect($parser->parses)->toBe(1);
});

it('evicts the oldest cached expression when the bound is reached', function (): void {
    $parser = countingExpressionParser();
    $cache = new AstCache(max: 2);
    $evaluator = new ExpressionEvaluator(new JsonPointer(), $cache, $parser);
    $context = new EvaluationContext(new WorkflowContext('def_1', ['a' => 1, 'b' => 2, 'c' => 3]));

    $a = new Expression('{$inputs.a}');
    $b = new Expression('{$inputs.b}');
    $c = new Expression('{$inputs.c}');

    expect($evaluator->evaluate($a, $context))->toBe(1);
    expect($evaluator->evaluate($b, $context))->toBe(2);
    expect($evaluator->evaluate($a, $context))->toBe(1);
    expect($evaluator->evaluate($c, $context))->toBe(3);
    expect($evaluator->evaluate($a, $context))->toBe(1);

    expect($parser->parses)->toBe(4)
        ->and($cache->count())->toBe(2);
});

it('does not cache an expression that fails to parse', function (): void {
    $parser = countingExpressionParser();
    $cache = new AstCache();
    $evaluator = new ExpressionEvaluator(new JsonPointer(), $cache, $parser);
    $context = new EvaluationContext(new WorkflowContext('def_1'));

    $threw = 0;
    for ($i = 0; $i < 2; $i++) {
        try {
            $evaluator->evaluate(new Expression('{$inputs}'), $context);
        } catch (ExpressionSyntaxException) {
            $threw++;
        }
    }

    expect($threw)->toBe(2)
        ->and($parser->parses)->toBe(2)
        ->and($cache->count())->toBe(0);
});

it('reuses one evaluator per dependency provider so its cache survives', function (): void {
    $provider = new ExpressionDependencyProvider();

    expect($provider->getExpressionEvaluator())->toBe($provider->getExpressionEvaluator())
        ->and($provider->getSelectorEvaluator())->toBe($provider->getSelectorEvaluator());
});

it('can be constructed with defaults for existing call sites', function (): void {
    expect(new ExpressionEvaluator())->toBeInstanceOf(ExpressionEvaluator::class);
});
