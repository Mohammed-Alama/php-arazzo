<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

/*
 * Simple-condition semantics, pinned against the Arazzo 1.0.1 Criterion Object
 * (https://github.com/OAI/Arazzo-Specification/blob/main/versions/1.0.1.md#criterion-object).
 *
 * What the spec says:
 *   - "simple - where basic literals, operators, and loose comparisons are used".
 *   - Literals: boolean, null, number, string. "Strings MUST use single quotes
 *     (') around the string. To use a literal single quote, escape the literal
 *     single quote using an additional single quote ('')."
 *   - Operators: <, <=, >, >=, ==, !=, !, &&, ||, (), [], .
 *   - "String comparisons MUST be case insensitive."
 *   - Data Types: "integer as a type is also supported and is defined as a JSON
 *     number without a fraction or exponent part" — i.e. 3 and 3.0 are one
 *     number type, not two.
 *
 * Verdict table (one row per behaviour audited by #157):
 *
 * | # | Behaviour | Spec says | We do | Verdict |
 * |---|---|---|---|---|
 * | 1 | `==`/`!=` on strings | MUST be case-insensitive | `strcasecmp` (ASCII) | conformant |
 * | 2 | `3 == 3.0` (int vs float) | numbers are one JSON type; comparisons are loose | compared with `===` before #157, so 3 != 3.0 | divergent — fixed here |
 * | 3 | Numeric string vs number (`'3' == 3`) | only "loose comparisons" is stated; no coercion algorithm | numeric-looking strings are coerced to int/float | unspecified — pinned |
 * | 4 | `<`, `>`, `<=`, `>=` with a non-numeric side | no cross-type ordering is defined | false | unspecified — pinned |
 * | 5 | Truthiness of a standalone value | not defined; literals are typed but a condition is an assertion | `''`, `'0'`, `0`, `0.0`, `[]`, `null`, `false` are false | unspecified — pinned |
 * | 6 | `&&`/`||` short-circuit | not stated | short-circuits: the right operand is not evaluated | unspecified — pinned |
 * | 7 | `''` inside a single-quoted string | MUST escape a literal `'` as `''` | the first `'` closed the literal, so `'it''s'` was a syntax error | divergent — fixed here |
 * | 8 | Double-quoted string literals | authors MUST use single quotes | accepted as well | lenient extension — pinned, not a misreading of conformant documents |
 * | 9 | Non-ASCII case folding | "case insensitive", with no folding algorithm named | ASCII-only `strcasecmp` | unspecified — pinned as a limitation |
 *
 * The spec defines no ABNF for simple conditions: the only ABNF is the Runtime
 * Expressions grammar, which does not describe conditions. This task therefore
 * authors no normative condition grammar and vendors no ABNF.
 */

function comparisonContext(): WorkflowContext
{
    return (new WorkflowContext('def_1'))
        ->withStepResponse('s1', [
            'statusCode' => 200,
            'headers' => [],
            'body' => [
                'status' => 'OK',
                'n' => 3,
                'f' => 3.0,
                'quote' => "it's",
                'empty' => [],
                'zeroString' => '0',
                'nil' => null,
            ],
        ]);
}

function evalComparison(string $condition, ?ExpressionEngineInterface $engine = null): bool
{
    return (new ConditionEvaluator($engine ?? new ExpressionEngine()))
        ->evaluate($condition, comparisonContext(), 's1');
}

it('pins string equality and inequality as case-insensitive (spec: MUST)', function (): void {
    expect(evalComparison('$response.body#/status == \'ok\''))->toBeTrue()
        ->and(evalComparison('$response.body#/status == \'Ok\''))->toBeTrue()
        ->and(evalComparison('$response.body#/status != \'ok\''))->toBeFalse()
        ->and(evalComparison('$response.body#/status != \'error\''))->toBeTrue();
});

it('treats an integer and a float of the same value as equal (spec: one JSON number type)', function (): void {
    // Divergent before #157: `3 === 3.0` is false in PHP, but the spec's
    // Data Types treat 3 and 3.0 as the same JSON number, compared loosely.
    expect(evalComparison('$response.body#/n == 3.0'))->toBeTrue()
        ->and(evalComparison('$response.body#/f == 3'))->toBeTrue()
        ->and(evalComparison('3 == 3.0'))->toBeTrue()
        ->and(evalComparison('$response.body#/n != 3.0'))->toBeFalse();
});

it('pins numeric-string coercion for loose equality (spec: unspecified)', function (): void {
    expect(evalComparison('$response.body#/n == \'3\''))->toBeTrue()
        ->and(evalComparison('\'3\' == 3'))->toBeTrue()
        ->and(evalComparison('\'3.0\' == 3'))->toBeTrue()
        // Exponent notation is a valid JSON number but not a numeric string here.
        ->and(evalComparison('\'1e3\' == 1000'))->toBeFalse()
        ->and(evalComparison('\'abc\' == 0'))->toBeFalse();
});

it('pins ordered comparison as false when either side is not numeric (spec: unspecified)', function (): void {
    // Numeric strings coerce; two strings get no lexicographic ordering, so the
    // case-insensitivity rule cannot even be applied to '<' between strings.
    expect(evalComparison('\'10\' > 9'))->toBeTrue()
        ->and(evalComparison('$response.body#/status < \'ZZZ\''))->toBeFalse()
        ->and(evalComparison('\'abc\' < \'abd\''))->toBeFalse()
        ->and(evalComparison('\'A\' < \'a\''))->toBeFalse()
        ->and(evalComparison('$response.body#/nil <= 0'))->toBeFalse();
});

it('pins the truthiness set (spec: unspecified)', function (): void {
    expect(evalComparison('\'\''))->toBeFalse()
        ->and(evalComparison('\'0\''))->toBeFalse()
        ->and(evalComparison('0'))->toBeFalse()
        ->and(evalComparison('0.0'))->toBeFalse()
        ->and(evalComparison('null'))->toBeFalse()
        ->and(evalComparison('false'))->toBeFalse()
        ->and(evalComparison('$response.body#/empty'))->toBeFalse()
        ->and(evalComparison('$response.body#/nil'))->toBeFalse()
        ->and(evalComparison('\'false\''))->toBeTrue()
        ->and(evalComparison('$response.body#/status'))->toBeTrue()
        ->and(evalComparison('$response.body#/n'))->toBeTrue();
});

it('short-circuits && and || without evaluating the right operand (spec: unspecified)', function (): void {
    $calls = 0;
    $engine = $this->createMock(ExpressionEngineInterface::class);
    $engine->method('evaluate')->willReturnCallback(function () use (&$calls): string {
        $calls++;

        return 'evaluated';
    });

    expect(evalComparison('true || $inputs.unreachable', $engine))->toBeTrue()
        ->and($calls)->toBe(0)
        ->and(evalComparison('false && $inputs.unreachable', $engine))->toBeFalse()
        ->and($calls)->toBe(0);
});

it('supports the spec escape for a literal single quote', function (): void {
    // Divergent before #157: the lexer closed the literal at the first quote,
    // so the spec's `''` escape produced a syntax error.
    expect(evalComparison('$response.body#/quote == \'it\'\'s\''))->toBeTrue()
        ->and(evalComparison('\'it\'\'s\' == \'it\'\'s\''))->toBeTrue()
        ->and(evalComparison('\'it\'\'s\' != \'its\''))->toBeTrue();
});

it('accepts double-quoted literals as a lenient extension (spec: authors MUST use single quotes)', function (): void {
    expect(evalComparison('$response.body#/status == "OK"'))->toBeTrue()
        ->and(evalComparison('$response.body#/quote == "it\'s"'))->toBeTrue();
});

it('pins non-ASCII case comparison to ASCII folding (spec: unspecified algorithm)', function (): void {
    // There is no case-folding algorithm in the spec and no mbstring requirement
    // in this package, so only ASCII case is folded. Pinned, not claimed.
    expect(evalComparison('\'Ä\' == \'ä\''))->toBeFalse()
        ->and(evalComparison('\'OK\' == \'ok\''))->toBeTrue();
});
