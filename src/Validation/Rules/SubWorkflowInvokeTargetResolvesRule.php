<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\Action\SubWorkflowFailureAction;
use Alama\Arazzo\Dto\Action\SubWorkflowSuccessAction;
use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SubWorkflowInvokeTargetResolvesRule implements Rule
{
    public function code(): string
    {
        return 'subworkflow.invoke_target_resolves';
    }

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
}
