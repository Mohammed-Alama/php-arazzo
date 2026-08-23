<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Expression\Ast\ExpressionAst;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;

final readonly class Expression
{
    public function __construct(public string $raw)
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
        try {
            return (new ExpressionParser())->parse($this->raw);
        } catch (ExpressionSyntaxException $e) {
            return $e;
        }
    }
}
