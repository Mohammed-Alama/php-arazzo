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
final class ExpressionUnresolvedWorkflowRefRule implements Rule
{
    public function __construct(private readonly ExpressionEngineInterface $engine) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ((new ExpressionWalker())->walk($doc, $symbols) as $site) {
            $ref = $this->engine->expressionReferences($site->expression->raw);
            if ($ref === null || $ref->kind !== ReferenceKind::Workflow) {
                continue;
            }

            $target = $symbols->workflows[$ref->target] ?? null;
            if ($target === null) {
                $errors->error($this->code(), "Expression references unknown workflow '{$ref->target}'.", $site->pointer);

                continue;
            }
            if ($site->workflow !== null && !isset($site->workflow->dependsOn[$ref->target])) {
                $errors->error($this->code(), "Expression references workflow '{$ref->target}' which is not in dependsOn.", $site->pointer);

                continue;
            }
            $bag = $ref->part === 'inputs' ? $target->inputs : $target->outputs;
            if (!isset($bag[$ref->name])) {
                $errors->error($this->code(), "Workflow '{$ref->target}' has no {$ref->part}.{$ref->name}.", $site->pointer);
            }
        }
    }

    public function code(): string
    {
        return 'expr.unresolved_workflow_ref';
    }
}
