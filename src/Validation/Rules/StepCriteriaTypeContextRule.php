<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\CriterionType;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

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
