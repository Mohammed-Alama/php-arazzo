<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionSyntaxRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = (new ExpressionParser())->parseOrError($site->expression->raw);
            if ($ast instanceof ExpressionSyntaxException) {
                $errors->error($this->code(), $ast->getMessage(), $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.syntax';
    }
}
