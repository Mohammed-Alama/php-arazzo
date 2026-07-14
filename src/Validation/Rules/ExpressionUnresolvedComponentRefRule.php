<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\Ast\ComponentRef;
use Alama\LaravelArazzo\Expression\ExpressionSyntaxException;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;
use Alama\LaravelArazzo\Validation\Support\ExpressionWalker;

final class ExpressionUnresolvedComponentRefRule implements Rule
{
    public function code(): string { return 'expr.unresolved_component_ref'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ast = $site->expression->astOrError();
            if ($ast instanceof ExpressionSyntaxException) continue;
            if (!$ast instanceof ComponentRef) continue;

            $bag = $symbols->components[$ast->type] ?? null;
            if ($bag === null || !isset($bag[$ast->name])) {
                $errors->error($this->code(), "Component reference '{$ast->type}.{$ast->name}' is not declared.", $site->pointer);
            }
        }
    }
}
