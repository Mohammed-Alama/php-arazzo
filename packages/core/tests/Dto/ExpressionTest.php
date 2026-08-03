<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto;

use Alama\Arazzo\Dto\Expression;
use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;

it('caches parsed AST across calls', function (): void {
    $e = new Expression('{$inputs.userId}');
    $a = $e->astOrError();
    $b = $e->astOrError();
    expect($a)->toBe($b)->toBeInstanceOf(ExpressionAst::class);
});

it('ast() throws when raw expression is malformed', function (): void {
    $e = new Expression('{$broken');
    expect(fn () => $e->ast())->toThrow(ExpressionSyntaxException::class);
    // Second call still hits the cached error path
    expect($e->astOrError())->toBeInstanceOf(ExpressionSyntaxException::class);
});
