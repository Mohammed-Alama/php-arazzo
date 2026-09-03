<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\CriterionType;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class StepSuccessCriteriaConditionRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                foreach ($s->successCriteria as $k => $c) {
                    $condition = trim($c->condition);
                    if ($condition === '') {
                        $errors->error(
                            $this->code(),
                            "successCriteria[{$k}].condition must not be empty or whitespace.",
                            "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                        );

                        continue;
                    }

                    $type = $c->type ?? CriterionType::Simple;
                    if ($type === CriterionType::Simple) {
                        // Look for a valid operator: ==, !=, >, <, >=, <=, ^=
                        if (!preg_match('/(==|!=|<=|>=|<|>|\^=)/', $condition)) {
                            if (preg_match('/[^=!<>^]=([^=]|$)/', $condition)) {
                                $errors->error(
                                    $this->code(),
                                    "successCriteria[{$k}].condition uses invalid assignment operator '='. Use '==' for equality.",
                                    "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                                );
                            } else {
                                $errors->error(
                                    $this->code(),
                                    "successCriteria[{$k}].condition is missing a valid operator (==, !=, <, >, <=, >=, ^=).",
                                    "/workflows/{$i}/steps/{$j}/successCriteria/{$k}/condition",
                                );
                            }
                        }
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
