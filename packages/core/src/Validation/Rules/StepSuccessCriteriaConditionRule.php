<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;

final class StepSuccessCriteriaConditionRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if (trim($c->condition) === '') {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}].condition must not be empty or whitespace.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.success_criteria_condition';
    }
}
