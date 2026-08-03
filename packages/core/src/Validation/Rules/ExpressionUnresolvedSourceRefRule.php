<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\Ast\SourceRef;
use Alama\Arazzo\Expression\ExpressionSyntaxException;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;
use Alama\Arazzo\Validation\Support\ExpressionWalker;

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
