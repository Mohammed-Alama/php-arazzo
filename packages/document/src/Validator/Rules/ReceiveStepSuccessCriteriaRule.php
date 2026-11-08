<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ReceiveStepSuccessCriteriaRule implements Rule
{
    public function __construct(public readonly bool $strict = true) {}

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // AsyncAPI fields (and therefore receive steps) require 1.1.0+, so a
        // 1.0 document can never legally declare one; leave that report to
        // AsyncApiFieldsRequire11Rule instead of duplicating it here.
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $workflow) {
            foreach ($workflow->steps as $si => $step) {
                if ($step->target->action !== 'receive' || $step->io->successCriteria !== []) {
                    continue;
                }

                $message = "receive step '{$step->stepId}' has no successCriteria; "
                    .'the default only requires a message to arrive.';
                $path = "/workflows/{$wi}/steps/{$si}/successCriteria";

                if ($this->strict) {
                    $errors->error($this->code(), $message, $path);
                } else {
                    $errors->warning($this->code(), $message, $path);
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.receive_missing_success_criteria';
    }
}
