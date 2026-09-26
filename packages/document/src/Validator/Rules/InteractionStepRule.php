<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\InteractionMode;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * interaction-step rules (PR #568):
 *  - prompt is required
 *  - form and redirect modes require inputSchema
 *  - acknowledge must not have inputSchema
 *  - redirect mode requires exactly one of redirectOperationId or redirectUrl
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class InteractionStepRule implements Rule
{
    public function code(): string
    {
        return 'step.interaction_step';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach ($w->steps as $j => $s) {
                $interaction = $s->target->interaction;
                if ($interaction === null) {
                    continue;
                }

                $path = "/workflows/{$i}/steps/{$j}";

                // Prompt is required
                if ($interaction->prompt === null || trim($interaction->prompt) === '') {
                    $errors->error('step.interaction_step', "Step '{$s->stepId}' interaction requires a prompt.", $path);
                }

                $mode = $interaction->mode;

                if ($mode === InteractionMode::Form || $mode === InteractionMode::Redirect) {
                    if ($interaction->inputSchema === null) {
                        $errors->error('step.interaction_step', "Step '{$s->stepId}' interaction mode {$mode->value} requires inputSchema.", $path);
                    }
                }

                if ($mode === InteractionMode::Acknowledge && $interaction->inputSchema !== null) {
                    $errors->error('step.interaction_step', "Step '{$s->stepId}' interaction mode Acknowledge must not have inputSchema.", $path);
                }

                if ($mode === InteractionMode::Redirect) {
                    if (!isset($interaction->redirectOperationId)) {
                        $errors->error('step.interaction_step', "Step '{$s->stepId}' redirect interaction requires redirectOperationId.", $path);
                    }
                }
            }
        }
    }
}
