<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Enum\TokenKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Lexer;

it('tokenises inputs.userId', function (): void {
    $tokens = (new Lexer())->tokenize('{$inputs.userId}');
    $kinds = array_map(fn ($t) => $t->kind, $tokens);
    $values = array_map(fn ($t) => $t->value, $tokens);

    expect($kinds)->toBe([TokenKind::Keyword, TokenKind::Dot, TokenKind::Name])
        ->and($values)->toBe(['inputs', '.', 'userId']);
});

it('tokenises response.body with json pointer', function (): void {
    $t = (new Lexer())->tokenize('{$response.body#/data/0/id}');
    expect($t[0]->kind)->toBe(TokenKind::Keyword)
        ->and($t[0]->value)->toBe('response')
        ->and($t[2]->kind)->toBe(TokenKind::Keyword)
        ->and($t[2]->value)->toBe('body')
        ->and($t[3]->kind)->toBe(TokenKind::Hash);
});

it('tokenises steps.fetch.outputs.user', function (): void {
    $t = (new Lexer())->tokenize('{$steps.fetch.outputs.user}');
    expect(count($t))->toBe(7);
});

it('tokenises dollar signs inside expressions', function (): void {
    $t = (new Lexer())->tokenize('{$response.$body}'); // Just to test the tokenizer
    expect($t[0]->kind)->toBe(TokenKind::Keyword)
        ->and($t[2]->kind)->toBe(TokenKind::Dollar)
        ->and($t[2]->offset)->toBe(11); // '{$response.$' -> offset 11
});

it('tokenises extended characters like %, @, +, :', function (): void {
    $t = (new Lexer())->tokenize('{$components.schemas.User%20Profile@v1+new:id}');
    $values = array_map(fn ($tok) => $tok->value, $t);
    expect($values)->toBe(['components', '.', 'schemas', '.', 'User%20Profile@v1+new:id']);
});

it('accepts expressions without braces', function (): void {
    $t = (new Lexer())->tokenize('$inputs.x');
    $values = array_map(fn ($tok) => $tok->value, $t);
    expect($values)->toBe(['inputs', '.', 'x']);
});

it('rejects missing dollar sign', function (): void {
    (new Lexer())->tokenize('inputs.x');
})->throws(ExpressionSyntaxException::class);

it('rejects illegal characters', function (): void {
    (new Lexer())->tokenize('{$inputs.na me}');
})->throws(ExpressionSyntaxException::class);
