<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Dto;

use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

it('ast() throws when raw expression is malformed', function (): void {
    $e = new Expression('{$broken');
    expect(fn () => (new ExpressionParser())->parse($e->raw))->toThrow(ExpressionSyntaxException::class);
    // Second call still hits the cached error path
    expect((new ExpressionParser())->parseOrError($e->raw))->toBeInstanceOf(ExpressionSyntaxException::class);
});
