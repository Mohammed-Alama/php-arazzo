<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Evaluation;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\Condition\Ast\Comparison;
use Alama\Arazzo\Evaluation\Condition\Ast\Index;
use Alama\Arazzo\Evaluation\Condition\Ast\Member;
use Alama\Arazzo\Evaluation\Condition\Ast\RuntimeExpr;
use Alama\Arazzo\Evaluation\Condition\ConditionEvaluator;
use Alama\Arazzo\Evaluation\Condition\ConditionSyntaxException;
use Alama\Arazzo\Evaluation\Condition\Lexer;
use Alama\Arazzo\Evaluation\Condition\Parser;
use Alama\Arazzo\Expression\ExpressionEngine;

/*
 * Simple-condition operand accessors, pinned against the Arazzo 1.1.0
 * Criterion Object
 * (https://github.com/OAI/Arazzo-Specification/blob/main/versions/1.1.0.md#criterion-object).
 *
 * What the spec says:
 *   - The Operators table lists `[]` as "Index (0-based)" and `.` as
 *     "Property de-reference".
 *   - Simple Conditions "MUST be an expression that combines Runtime
 *     Expressions, literals, and operators"; `null` evaluates to falsy.
 *
 * The runtime-expression grammar itself has no accessor syntax, so the operand
 * is split into the longest valid runtime-expression prefix and the trailing
 * accessor chain. The spec does not define a missing member, an out-of-range
 * index, or an accessor on a non-array value; those are pinned here as null.
 *
 * Verdict table (one row per behaviour audited by #158):
 *
 * | # | Behaviour | Spec says | We do | Verdict |
 * |---|---|---|---|---|
 * | 1 | `$response.body.items[0].id` | `[]` index + `.` de-reference | index then member | conformant |
 * | 2 | Missing member | unspecified | null | unspecified — pinned |
 * | 3 | Out-of-range index | unspecified | null | unspecified — pinned |
 * | 4 | Accessor on a non-array | unspecified | null | unspecified — pinned |
 * | 5 | Bare accessor operand | `null` is falsy | truthiness of the resolved value | conformant |
 * | 6 | Accessors under `&&`/`||`/`!`/`()` | operators listed | normal precedence | conformant |
 * | 7 | JSON-pointer base + accessor | runtime expressions are operands | pointer then accessors | conformant |
 * | 8 | `${...}` / `{$...}` spellings | both wrappers accepted | same accessor handling | conformant |
 * | 9 | Malformed accessor | no accessor grammar stated | ConditionSyntaxException | unspecified — pinned |
 */

function conditionAccessorContext(): WorkflowContext
{
    return (new WorkflowContext('def_1'))
        ->withInputs([
            'user' => ['name' => 'Alice', 'roles' => ['admin', 'editor']],
        ])
        ->withStepResponse('s1', [
            'statusCode' => 200,
            'headers' => ['X-Mode' => 'Live'],
            'body' => [
                'items' => [
                    ['id' => 1, 'name' => 'one'],
                    ['id' => 2, 'name' => 'two'],
                ],
                'status' => 'OK',
                'scalar' => 'text',
                'nil' => null,
                'nested' => ['a' => ['b' => 42]],
            ],
        ]);
}

function evalConditionAccessor(string $condition): bool
{
    return (new ConditionEvaluator(new ExpressionEngine()))
        ->evaluate($condition, conditionAccessorContext(), 's1');
}

it('records the accessor chain on the runtime-expression AST (spec: [] and .)', function (): void {
    $ast = (new Parser(new Lexer()))->parse('$response.body.items[0].id == 1');

    expect($ast)->toBeInstanceOf(Comparison::class);

    $left = $ast->left;
    expect($left)->toBeInstanceOf(RuntimeExpr::class)
        ->and($left->expression->raw)->toBe('{$response.body}')
        ->and($left->accessors)->toHaveCount(3)
        ->and($left->accessors[0])->toEqual(new Member('items'))
        ->and($left->accessors[1])->toEqual(new Index(0))
        ->and($left->accessors[2])->toEqual(new Member('id'));
});

it('splits at the longest valid runtime-expression prefix (spec: runtime expressions)', function (): void {
    $ast = (new Parser(new Lexer()))->parse('$steps.fetch.outputs.body.items[0]');

    expect($ast)->toBeInstanceOf(RuntimeExpr::class)
        ->and($ast->expression->raw)->toBe('{$steps.fetch.outputs.body}')
        ->and($ast->accessors)->toHaveCount(2)
        ->and($ast->accessors[0])->toEqual(new Member('items'))
        ->and($ast->accessors[1])->toEqual(new Index(0));
});

it('dereferences a property after a zero-based index (spec: [] and .)', function (): void {
    expect(evalConditionAccessor('$response.body.items[0].id == 1'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.items[1].name == \'two\''))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.items[0].id == 2'))->toBeFalse()
        ->and(evalConditionAccessor('$response.body.nested.a.b == 42'))->toBeTrue();
});

it('pins a missing member to null (spec: unspecified)', function (): void {
    expect(evalConditionAccessor('$response.body.missing == null'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.missing != null'))->toBeFalse()
        ->and(evalConditionAccessor('$response.body.items[0].missing == null'))->toBeTrue();
});

it('pins an out-of-range index to null (spec: unspecified)', function (): void {
    expect(evalConditionAccessor('$response.body.items[9] == null'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.items[9].id == null'))->toBeTrue();
});

it('pins an accessor on a non-array value to null (spec: unspecified)', function (): void {
    expect(evalConditionAccessor('$response.body.scalar.name == null'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.scalar[0] == null'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.nil.name == null'))->toBeTrue();
});

it('evaluates a bare accessor operand by truthiness (spec: null is falsy)', function (): void {
    expect(evalConditionAccessor('$response.body.items[0].id'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.items[9].id'))->toBeFalse()
        ->and(evalConditionAccessor('!$response.body.items[9].id'))->toBeTrue();
});

it('composes accessors with &&, ||, ! and parentheses (spec: operators)', function (): void {
    expect(evalConditionAccessor('$response.body.items[0].id == 1 && $response.body.items[1].id == 2'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body.items[0].id == 9 || $response.body.items[1].id == 2'))->toBeTrue()
        ->and(evalConditionAccessor('!($response.body.items[0].id == 9)'))->toBeTrue()
        ->and(evalConditionAccessor('($response.body.items[0].id == 1 || $response.body.items[0].id == 2) && $response.body.status == \'OK\''))->toBeTrue();
});

it('dereferences properties on $inputs values (spec: runtime expressions)', function (): void {
    expect(evalConditionAccessor('$inputs.user.roles[0] == \'admin\''))->toBeTrue()
        ->and(evalConditionAccessor('$inputs.user.name == \'Alice\''))->toBeTrue()
        ->and(evalConditionAccessor('$inputs.user.roles[9] == null'))->toBeTrue();
});

it('applies accessors after a JSON-pointer base (spec: runtime expressions)', function (): void {
    expect(evalConditionAccessor('$response.body#/items/0.id == 1'))->toBeTrue()
        ->and(evalConditionAccessor('$response.body#/items/1.name == \'two\''))->toBeTrue()
        ->and(evalConditionAccessor('$response.body#/items/9.id == null'))->toBeTrue();
});

it('applies accessors in both braced spellings (spec: runtime expressions)', function (): void {
    expect(evalConditionAccessor('${response.body.items[0].id} == 1'))->toBeTrue()
        ->and(evalConditionAccessor('{$response.body.items[1].id} == 2'))->toBeTrue();
});

it('rejects malformed accessors (spec: no accessor grammar stated)', function (string $condition): void {
    (new ConditionEvaluator(new ExpressionEngine()))
        ->evaluate($condition, conditionAccessorContext(), 's1');
})->throws(ConditionSyntaxException::class)->with([
    'non-numeric index' => ['$response.body.items[abc] == 1'],
    'unterminated index' => ['$response.body.items[0 == 1'],
    'empty index' => ['$response.body.items[] == 1'],
    'dangling member' => ['$response.body.items. == 1'],
    'negative index' => ['$response.body.items[-1] == 1'],
]);
