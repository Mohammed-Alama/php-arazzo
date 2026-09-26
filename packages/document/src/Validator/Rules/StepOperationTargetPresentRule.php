<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepOperationTargetPresentRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $target = $s->target;
                $fields = [
                    'operationId' => $target->operationId,
                    'operationPath' => $target->operationPath,
                    'workflowId' => $target->workflowId,
                    'operationName' => $target->operationName,
                    'rpcMethod' => $target->rpcMethod,
                    'interaction' => $target->interaction,
                    'graphqlOperation' => $target->graphqlOperation,
                ];
                $set = array_filter($fields, static fn ($v) => $v !== null);
                if (count($set) !== 1) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' must set exactly one of operationId, operationPath, workflowId, operationName, rpcMethod, interaction, graphqlOperation (got ".count($set).').',
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
