<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class StepNestedWorkflowExistsRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                if ($s->workflowId === null) {
                    continue;
                }
                if (!isset($symbols->workflows[$s->workflowId])) {
                    if (!$doc->hasExternalSourceFor($s->workflowId)) {
                        $errors->error(
                            $this->code(),
                            "step.workflowId '{$s->workflowId}' does not resolve to a declared local workflow or an external arazzo source.",
                            "/workflows/{$i}/steps/{$j}/workflowId",
                        );
                    }
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.nested_workflow_exists';
    }
}
