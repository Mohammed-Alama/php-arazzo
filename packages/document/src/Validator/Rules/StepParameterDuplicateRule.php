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
final class StepParameterDuplicateRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // Check for duplicate parameters within each step
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $this->checkStepParameters($s->io->parameters, $errors, "/workflows/{$wi}/steps/{$si}/parameters");
            }
        }

        // Check for duplicate parameters within workflow parameters scope
        foreach ($doc->workflows as $wi => $w) {
            $this->checkStepParameters($w->parameters, $errors, "/workflows/{$wi}/parameters");
        }
    }

    /**
     * @param  list<Parameter|Reusable>  $params
     */
    private function checkStepParameters(array $params, ErrorCollector $errors, string $base): void
    {
        // Filter out reusable parameters - they are resolved externally
        $nonReusableParams = [];
        foreach ($params as $pi => $p) {
            if ($p instanceof Reusable) {
                continue;
            }
            $nonReusableParams[] = $p;
        }

        // Check for duplicate (name, in) pairs within the same scope
        $seen = [];
        foreach ($nonReusableParams as $pi => $p) {
            $key = $p->name.':'.($p->in?->value ?? 'null'); // phpstan:ignore nullsafe.neverNull
            if (isset($seen[$key])) {
                $errors->error(
                    $this->code(),
                    "Parameter '{$p->name}' with 'in' value '{$p->in?->value}' is duplicated.",
                    "{$base}/{$pi}",
                );
            }
            $seen[$key] = $pi;
        }
    }

    public function code(): string
    {
        return 'step.parameter_duplicate';
    }
}
