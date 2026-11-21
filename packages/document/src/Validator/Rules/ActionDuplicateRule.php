<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\Action\Action;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * Rejects duplicate success/failure action names within a single list.
 *
 * Action identity is `name` (the spec leaves "duplicate" undefined for actions;
 * identity is inferred from action selection by name). Reusable action entries
 * are skipped, mirroring `StepParameterMerger` where reusables never collide.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ActionDuplicateRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            // Step-level success/failure actions
            foreach ($w->steps as $si => $s) {
                $this->checkActions(
                    $s->flow->onSuccess,
                    $errors,
                    "/workflows/{$wi}/steps/{$si}/onSuccess",
                    'step.actions_duplicate',
                );
                $this->checkActions(
                    $s->flow->onFailure,
                    $errors,
                    "/workflows/{$wi}/steps/{$si}/onFailure",
                    'step.actions_duplicate',
                );
            }

            // Workflow-level success/failure actions
            $this->checkActions(
                $w->successActions,
                $errors,
                "/workflows/{$wi}/successActions",
                'workflow.actions_duplicate',
            );
            $this->checkActions(
                $w->failureActions,
                $errors,
                "/workflows/{$wi}/failureActions",
                'workflow.actions_duplicate',
            );
        }
    }

    /**
     * @param  list<Action|Reusable>  $actions  Each entry is an action object with a
     *                                          `name` property, or a `Reusable`.
     */
    private function checkActions(array $actions, ErrorCollector $errors, string $base, string $code): void
    {
        $seen = [];
        foreach ($actions as $ai => $action) {
            if ($action instanceof Reusable) {
                continue; // reusables never collide by name
            }

            $name = $action->name;
            if (isset($seen[$name])) {
                $errors->error(
                    $code,
                    "Action '{$name}' is duplicated at indices {$seen[$name]} and {$ai}.",
                    $base,
                );
            }
            $seen[$name] = $ai;
        }
    }

    public function code(): string
    {
        return 'step.actions_duplicate';
    }
}
