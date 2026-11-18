<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepParameterRequiresInRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                // Only HTTP operation steps require `in`; workflow steps and
                // workflow-level parameters may pass values without it.
                if ($s->target->operationId === null && $s->target->operationPath === null) {
                    continue;
                }

                $this->checkStepParameters($s->io->parameters, $errors, "/workflows/{$wi}/steps/{$si}/parameters");
            }
        }
    }

    /**
     * @param  list<Parameter|Reusable>  $params
     */
    private function checkStepParameters(array $params, ErrorCollector $errors, string $base): void
    {
        foreach ($params as $pi => $p) {
            if ($p instanceof Reusable) {
                continue;
            }

            if ($p->in === null) {
                $errors->error(
                    $this->code(),
                    "Parameter '{$p->name}' must have an 'in' value.",
                    "{$base}/{$pi}/in",
                );
            }
        }
    }

    public function code(): string
    {
        return 'step.parameter_requires_in';
    }
}
