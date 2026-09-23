<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Document\Validator\Support\ExpressionWalker;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExpressionUnresolvedComponentRefRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null || $ref->kind !== ReferenceKind::Component) {
                continue;
            }

            $bag = $symbols->components[$ref->target] ?? null;
            if ($bag === null || !isset($bag[$ref->name])) {
                $errors->error($this->code(), "Component reference '{$ref->target}.{$ref->name}' is not declared.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_component_ref';
    }
}
