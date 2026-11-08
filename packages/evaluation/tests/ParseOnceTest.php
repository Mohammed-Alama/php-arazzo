<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Condition\ConditionCache;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\Condition\Lexer;
use Alama\Arazzo\Evaluation\Condition\Parser as ConditionParser;
use Alama\Arazzo\Evaluation\Condition\ParserInterface as ConditionParserInterface;
use Alama\Arazzo\Evaluation\Interfaces\ConditionNode;
use Alama\Arazzo\Expression\ExpressionEngine;
use ReflectionClass;
use ReflectionProperty;

final class CountingConditionParser implements ConditionParserInterface
{
    public int $parses = 0;

    public function __construct(private readonly ConditionParserInterface $inner) {}

    public function parse(string $condition): ConditionNode
    {
        $this->parses++;

        return $this->inner->parse($condition);
    }
}

function parseOnceContext(): WorkflowContext
{
    return (new WorkflowContext('def_1'))
        ->withStepResponse('s1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => ['status' => 'OK'],
        ]);
}

function countingConditionParser(): CountingConditionParser
{
    return new CountingConditionParser(new ConditionParser(new Lexer()));
}

it('parses a condition once across repeated evaluations', function (): void {
    $parser = countingConditionParser();
    $evaluator = new ConditionEvaluator(new ExpressionEngine(), parser: $parser);
    $context = parseOnceContext();

    expect($evaluator->evaluate('$statusCode == 200', $context, 's1'))->toBeTrue();
    expect($evaluator->evaluate('$statusCode == 200', $context, 's1'))->toBeTrue();
    expect($evaluator->evaluate('$statusCode == 200', $context, 's1'))->toBeTrue();

    expect($parser->parses)->toBe(1);
});

it('does not cache a condition that fails to parse', function (): void {
    $parser = countingConditionParser();
    $evaluator = new ConditionEvaluator(new ExpressionEngine(), parser: $parser);
    $context = parseOnceContext();

    $threw = 0;
    for ($i = 0; $i < 2; $i++) {
        try {
            $evaluator->evaluate('$statusCode ==', $context, 's1');
        } catch (ConditionSyntaxException) {
            $threw++;
        }
    }

    expect($threw)->toBe(2)
        ->and($parser->parses)->toBe(2);
});

it('evicts the oldest cached condition when the bound is reached', function (): void {
    $parser = countingConditionParser();
    $cache = new ConditionCache(max: 1);
    $evaluator = new ConditionEvaluator(new ExpressionEngine(), cache: $cache, parser: $parser);
    $context = parseOnceContext();

    $evaluator->evaluate('$statusCode == 200', $context, 's1');
    $evaluator->evaluate('$statusCode == 201', $context, 's1');
    $evaluator->evaluate('$statusCode == 200', $context, 's1');

    expect($parser->parses)->toBe(3)
        ->and($cache->count())->toBe(1);
});

it('condition cache is final with no static properties', function (): void {
    $ref = new ReflectionClass(ConditionCache::class);

    expect($ref->isFinal())->toBeTrue()
        ->and($ref->getProperties(ReflectionProperty::IS_STATIC))->toHaveCount(0);
});
