<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\Ast\ComponentRef;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;
use Alama\Arazzo\Validator\Support\ExpressionWalker;

final class ExpressionUnresolvedComponentRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof ComponentRef) {
                continue;
            }

            $bag = $symbols->components[$ast->type] ?? null;
            if ($bag === null || !isset($bag[$ast->name])) {
                $errors->error($this->code(), "Component reference '{$ast->type}.{$ast->name}' is not declared.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_component_ref';
    }
}
