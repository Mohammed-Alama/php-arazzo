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

final class ExpressionUnresolvedInputRefRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null || $ref->kind !== ReferenceKind::Input) {
                continue;
            }

            $syms = $site->workflow;
            if ($syms === null) {
                continue;
            }
            if (!isset($syms->inputs[$ref->target]) && !isset($syms->parameters[$ref->target])) {
                $errors->error(
                    $this->code(),
                    "Expression references unknown input '{$ref->target}'.",
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
