<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Contracts\Spec\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class SubWorkflowInvokeTargetResolvesRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        $localIds = array_map(fn ($w) => $w->workflowId, $doc->workflows);

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->steps as $si => $step) {
                foreach ($step->onSuccess as $ai => $action) {
                    if ($action instanceof SubWorkflowSuccessAction) {
                        $this->assertResolves($action->workflowId, $localIds, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess/{$ai}");
                    }
                }
                foreach ($step->onFailure as $ai => $action) {
                    if ($action instanceof SubWorkflowFailureAction) {
                        $this->assertResolves($action->workflowId, $localIds, $errors, "/workflows/{$wi}/steps/{$si}/onFailure/{$ai}");
                    }
                }
            }
        }

        foreach ($doc->components->successActions as $name => $action) {
            if ($action instanceof SubWorkflowSuccessAction) {
                $this->assertResolves($action->workflowId, $localIds, $errors, "/components/successActions/{$name}");
            }
        }
        foreach ($doc->components->failureActions as $name => $action) {
            if ($action instanceof SubWorkflowFailureAction) {
                $this->assertResolves($action->workflowId, $localIds, $errors, "/components/failureActions/{$name}");
            }
        }
    }

    /** @param list<string> $localIds */
    private function assertResolves(string $target, array $localIds, ErrorCollector $errors, string $pointer): void
    {
        if (in_array($target, $localIds, true)) {
            return;
        }

        $errors->error(
            $this->code(),
            "Sub-workflow invoke target '{$target}' does not resolve to any workflow in this document.",
            $pointer,
        );
    }

    public function code(): string
    {
        return 'subworkflow.invoke_target_resolves';
    }
}
