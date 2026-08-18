<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

final class StepOperationTargetPresentRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $set = (int) ($s->operationId !== null) + (int) ($s->operationPath !== null) + (int) ($s->workflowId !== null);
                if ($set !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId (got {$set}).",
                        "/workflows/{$i}/steps/{$j}",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.operation_target_present';
    }
}
