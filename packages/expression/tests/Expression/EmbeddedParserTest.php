<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use RuntimeException;

/**
 * Embedded-expression splitting is version-sensitive. The vendored grammars
 * disagree, so the behaviour is pinned per spec version:
 *
 * 1.0.x — prose only. `arazzo-runtime-expression-1.0.1.abnf` has no
 * `embedded-expression` / `expression-string` rule; the spec says only
 * "Expressions can be embedded into string values by surrounding the
 * expression with `{}` curly braces." The 1.0.1 `json-pointer` production's
 * `unescaped` range includes 0x7D (`}`), so `}` is grammatically legal inside
 * a pointer. The prose gives no delimiter rule for that case. Decision: close
 * at the first `}` (the historical behaviour); a JSON Pointer containing `}`
 * must therefore be used as a bare expression, not embedded. The Lexer
 * currently rejects `{`/`}` in names and pointers (a separate conformance gap,
 * deliberately not widened here).
 *
 * 1.1.x — `embedded-expression = "{" expression "}"`, and the JSON Pointer
 * `CHAR`/`unescaped` productions exclude `{`/`}` "for unambiguous embedded
 * expression parsing". A `}` therefore always terminates the expression.
 *
 * 1.2.x — no 1.2 ABNF is vendored yet, so it follows the 1.1.x rule set.
 *
 * `${...}` is a pre-existing library spelling that appears in neither ABNF.
 * It is kept for 1.0.x compatibility and is left as literal text under
 * 1.1.x+.
 */
function embeddedSyntaxError(callable $call): ExpressionSyntaxException
{
    try {
        $call();
    } catch (ExpressionSyntaxException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected an ExpressionSyntaxException');
}

/**
 * @return list<array{isExpression: bool, text: string, offset: int}>
 */
function embeddedSegments(string $template, SpecVersion $version = SpecVersion::V1_0): array
{
    return array_map(
        static fn ($segment): array => [
            'isExpression' => $segment->isExpression,
            'text' => $segment->text,
            'offset' => $segment->offset,
        ],
        (new ExpressionEngine())->parseEmbedded($template, $version),
    );
}

it('returns no segments for an empty template', function (): void {
    expect(embeddedSegments(''))->toBe([]);
});

it('returns one literal segment for a template without expressions', function (): void {
    expect(embeddedSegments('plain text'))->toBe([
        ['isExpression' => false, 'text' => 'plain text', 'offset' => 0],
    ]);
});

it('splits a template around a single expression', function (): void {
    expect(embeddedSegments('Hello {$inputs.name}!'))->toBe([
        ['isExpression' => false, 'text' => 'Hello ', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.name', 'offset' => 6],
        ['isExpression' => false, 'text' => '!', 'offset' => 20],
    ]);
});

it('splits multiple expressions and keeps literal offsets', function (): void {
    expect(embeddedSegments('a {$inputs.x} b {$steps.login.outputs.y} c'))->toBe([
        ['isExpression' => false, 'text' => 'a ', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.x', 'offset' => 2],
        ['isExpression' => false, 'text' => ' b ', 'offset' => 13],
        ['isExpression' => true, 'text' => '$steps.login.outputs.y', 'offset' => 16],
        ['isExpression' => false, 'text' => ' c', 'offset' => 40],
    ]);
});

it('splits adjacent expressions without an empty literal between them', function (): void {
    expect(embeddedSegments('{$inputs.x}{$inputs.y}'))->toBe([
        ['isExpression' => true, 'text' => '$inputs.x', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.y', 'offset' => 11],
    ]);
});

it('leaves a literal dollar and a literal brace alone', function (): void {
    expect(embeddedSegments('costs $5 and {not} {$inputs.x}'))->toBe([
        ['isExpression' => false, 'text' => 'costs $5 and {not} ', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.x', 'offset' => 19],
    ]);
});

it('keeps the ${...} spelling for 1.0.x', function (): void {
    expect(embeddedSegments('x ${inputs.name}'))->toBe([
        ['isExpression' => false, 'text' => 'x ', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.name', 'offset' => 2],
    ]);
});

it('does not treat ${...} as an expression under 1.1.0', function (): void {
    expect(embeddedSegments('x ${inputs.name}', SpecVersion::V1_1))->toBe([
        ['isExpression' => false, 'text' => 'x ${inputs.name}', 'offset' => 0],
    ]);
});

it('treats ${$...} as a literal dollar followed by a canonical expression', function (): void {
    // A lone `$` before a canonical `{$...}` token is literal text. This also
    // keeps the legacy `${...}` rule from swallowing the canonical token.
    expect(embeddedSegments('x ${$inputs.name}'))->toBe([
        ['isExpression' => false, 'text' => 'x $', 'offset' => 0],
        ['isExpression' => true, 'text' => '$inputs.name', 'offset' => 3],
    ]);
});

it('closes a }-containing pointer at the first } under both 1.0.x and 1.1.0', function (): void {
    // 1.0.1's grammar permits } in a pointer but has no delimiter prose;
    // 1.1.0 excludes it from the pointer. Both close at the first }.
    $expected = [
        ['isExpression' => true, 'text' => '$request.body#/a', 'offset' => 0],
        ['isExpression' => false, 'text' => 'b}', 'offset' => 18],
    ];

    expect(embeddedSegments('{$request.body#/a}b}', SpecVersion::V1_0))->toBe($expected)
        ->and(embeddedSegments('{$request.body#/a}b}', SpecVersion::V1_1))->toBe($expected)
        ->and(embeddedSegments('{$request.body#/a}b}', SpecVersion::V1_2))->toBe($expected);
});

it('treats a literal closing brace as text', function (): void {
    expect(embeddedSegments('a } b'))->toBe([
        ['isExpression' => false, 'text' => 'a } b', 'offset' => 0],
    ]);
});

it('throws on an unterminated embedded expression at its opening delimiter', function (): void {
    $error = embeddedSyntaxError(fn () => (new ExpressionEngine())->parseEmbedded('Hello {$inputs.name'));

    expect($error->offset)->toBe(6)
        ->and($error->expression)->toBe('Hello {$inputs.name');
});

it('throws on an unterminated legacy ${...} expression at its opening delimiter', function (): void {
    $error = embeddedSyntaxError(fn () => (new ExpressionEngine())->parseEmbedded('${inputs.name'));

    expect($error->offset)->toBe(0)
        ->and($error->expression)->toBe('${inputs.name');
});

it('throws on an invalid embedded expression at its opening delimiter', function (): void {
    $error = embeddedSyntaxError(fn () => (new ExpressionEngine())->parseEmbedded('Hello {$inputs.}!'));

    expect($error->offset)->toBe(6)
        ->and($error->expression)->toBe('Hello {$inputs.}!');
});

it('throws on a later invalid expression with its own absolute offset', function (): void {
    $error = embeddedSyntaxError(fn () => (new ExpressionEngine())->parseEmbedded('a {$inputs.name} b {$inputs.x'));

    expect($error->offset)->toBe(19)
        ->and($error->expression)->toBe('a {$inputs.name} b {$inputs.x');
});
