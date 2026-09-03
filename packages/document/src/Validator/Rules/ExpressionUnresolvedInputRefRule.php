<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\Ast\InputRef;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\Parser as ExpressionParser;
use Alama\Arazzo\Expression\SymbolTable;

final class ExpressionUnresolvedInputRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = (new ExpressionParser())->parseOrError($site->expression->raw);
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof InputRef) {
                continue;
            }

            $syms = $site->workflow;
            if ($syms === null) {
                continue;
            }
            if (!isset($syms->inputs[$ast->name]) && !isset($syms->parameters[$ast->name])) {
                $errors->error(
                    $this->code(),
                    "Expression references unknown input '{$ast->name}'.",
                    $site->pointer,
                );
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_input_ref';
    }
}
