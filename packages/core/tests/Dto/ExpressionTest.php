<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto;

use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Spec\Expression;

it('ast() throws when raw expression is malformed', function (): void {
    $e = new Expression('{$broken');
    expect(fn () => (new ExpressionParser())->parse($e->raw))->toThrow(ExpressionSyntaxException::class);
    // Second call still hits the cached error path
    expect((new ExpressionParser())->parseOrError($e->raw))->toBeInstanceOf(ExpressionSyntaxException::class);
});
