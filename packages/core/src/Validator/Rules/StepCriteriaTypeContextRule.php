<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\CriterionType;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class StepCriteriaTypeContextRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        $needsContext = [CriterionType::JsonPath, CriterionType::XPath, CriterionType::Regex];
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    if ($c->type !== null && in_array($c->type, $needsContext, true) && ($c->context === null || trim($c->context) === '')) {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}] type '{$c->type->value}' requires a context expression.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/context",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.criteria_type_context';
    }
}
