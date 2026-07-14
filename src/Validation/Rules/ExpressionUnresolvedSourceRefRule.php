<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\SourceRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedSourceRefRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) {
                continue;
            }
            if (!$ast instanceof SourceRef) {
                continue;
            }

            if (!isset($symbols->sourceDescriptions[$ast->name])) {
                $errors->error($this->code(), "Expression references unknown sourceDescription '{$ast->name}'.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_source_ref';
    }
}
