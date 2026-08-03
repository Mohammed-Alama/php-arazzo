<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class StepIdPatternRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if (preg_match('/^[A-Za-z0-9_\-]+$/', $s->stepId) !== 1) {
                    $errors->error(
                        $this->code(),
                        "stepId '{$s->stepId}' must match [A-Za-z0-9_-]+.",
                        "/workflows/{$i}/steps/{$j}/stepId",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.id_pattern';
    }
}
