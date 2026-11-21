<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Action\FailureGotoAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ActionParameterInForbiddenRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->flow->onSuccess as $ai => $action) {
                    $this->checkAction($action, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess/{$ai}", $doc);
                }
                foreach ($s->flow->onFailure as $ai => $action) {
                    $this->checkAction($action, $errors, "/workflows/{$wi}/steps/{$si}/onFailure/{$ai}", $doc);
                }
            }

            foreach ($w->successActions as $ai => $action) {
                $this->checkAction($action, $errors, "/workflows/{$wi}/successActions/{$ai}", $doc);
            }
            foreach ($w->failureActions as $ai => $action) {
                $this->checkAction($action, $errors, "/workflows/{$wi}/failureActions/{$ai}", $doc);
            }
        }
    }

    private function checkAction(
        SuccessAction|FailureAction|Reusable $action,
        ErrorCollector $errors,
        string $base,
        ArazzoDocument $doc,
    ): void {
        // Only goto actions carry a list of Parameter/Reusable action parameters;
        // end actions have none and invoke actions carry a plain input map.
        if (!$action instanceof SuccessGotoAction && !$action instanceof FailureGotoAction) {
            return;
        }

        // R2: Arazzo 1.0 does not define `parameters` on action objects at all.
        if ($doc->specVersion === SpecVersion::V1_0 && $action->parameters !== []) {
            $errors->error(
                $this->code(),
                'Action parameters are not allowed in Arazzo 1.0.0.',
                "{$base}/parameters",
            );

            return;
        }

        // R1: Arazzo 1.1 forbids `in` on action parameters.
        // C1: reusable parameter references are skipped; only inline Parameter
        // entries are inspected, and only when they declare an `in`.
        foreach ($action->parameters as $pi => $p) {
            if ($p instanceof Reusable || $p->in === null) {
                continue;
            }

            $errors->error(
                $this->code(),
                "Parameter '{$p->name}' with 'in' value '{$p->in->value}' MUST NOT be used on action parameters.",
                "{$base}/parameters/{$pi}/in",
            );
        }
    }

    public function code(): string
    {
        return 'action.parameter_in_forbidden';
    }
}
