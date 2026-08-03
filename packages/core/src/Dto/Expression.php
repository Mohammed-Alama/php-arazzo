<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

use Alama\LaravelArazzo\Expression\Ast\ExpressionAst;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\Parser as ExpressionParser;

final class Expression
{
    private ExpressionAst|ExpressionSyntaxException|null $cached = null;

    public function __construct(public readonly string $raw)
    {
    }

    public function ast(): ExpressionAst
    {
        $result = $this->astOrError();
        if ($result instanceof ExpressionSyntaxException) {
            throw $result;
        }

        return $result;
    }

    public function astOrError(): ExpressionAst|ExpressionSyntaxException
    {
        if ($this->cached !== null) {
            return $this->cached;
        }
        try {
            return $this->cached = (new ExpressionParser())->parse($this->raw);
        } catch (ExpressionSyntaxException $e) {
            return $this->cached = $e;
        }
    }
}
