<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\SuccessAction;
use Alama\Arazzo\Contracts\Spec\Action\SuccessGotoAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepSuccessActionParameterInRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                foreach ($s->flow->onSuccess as $ai => $action) {
                    $this->checkAction($action, $errors, "/workflows/{$wi}/steps/{$si}/onSuccess/{$ai}");
                }
            }

            foreach ($w->successActions as $ai => $action) {
                $this->checkAction($action, $errors, "/workflows/{$wi}/successActions/{$ai}");
            }
        }
    }

    private function checkAction(SuccessAction|Reusable $action, ErrorCollector $errors, string $base): void
    {
        // Only goto success actions carry a parameter list; end actions have
        // none, and invoke actions carry a plain input map (no `in` field).
        if (!$action instanceof SuccessGotoAction) {
            return;
        }

        foreach ($action->parameters as $pi => $p) {
            if ($p instanceof Reusable || $p->in === null) {
                continue;
            }

            $errors->error(
                $this->code(),
                "Parameter '{$p->name}' with 'in' value '{$p->in->value}' MUST NOT be used on success action parameters.",
                "{$base}/parameters/{$pi}/in",
            );
        }
    }

    public function code(): string
    {
        return 'step.success_action_parameter_in';
    }
}
