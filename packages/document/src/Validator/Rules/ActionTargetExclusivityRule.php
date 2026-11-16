<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ActionTargetExclusivityRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                $this->checkList($s->flow->onSuccess, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess");
                $this->checkList($s->flow->onFailure, $errors, "/workflows/{$wi}/steps/{$si}/onFailure");
            }
        }
    }

    /** @param list<mixed> $actions */
    private function checkList(array $actions, ErrorCollector $errors, string $base): void
    {
        foreach ($actions as $i => $a) {
            if (!($a instanceof SuccessGotoAction || $a instanceof FailureGotoAction || $a instanceof RetryAction)) {
                continue;
            }
            if ($a->stepId !== null && $a->workflowId !== null) {
                $errors->error(
                    $this->code(),
                    "Action '{$a->name}' declares both stepId and workflowId; they are mutually exclusive.",
                    "{$base}/{$i}",
                );
            }
        }
    }

    public function code(): string
    {
        return 'action.target_ambiguous';
    }
}
