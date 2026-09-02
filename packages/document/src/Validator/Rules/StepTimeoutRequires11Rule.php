<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

/**
 * The Step Object `timeout` field is an Arazzo 1.1 addition; it must not
 * appear on documents declaring 1.0.x, and its value must be positive.
 */
final class StepTimeoutRequires11Rule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $wi => $w) {
            foreach ($w->steps as $si => $s) {
                if ($s->timeout === null) {
                    continue;
                }

                if ($doc->specVersion === SpecVersion::V1_0) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' declares timeout, which requires Arazzo 1.1.0.",
                        "/workflows/{$wi}/steps/{$si}/timeout",
                    );
                }

                if ($s->timeout <= 0) {
                    $errors->error(
                        $this->code(),
                        "Step '{$s->stepId}' timeout must be a positive number of seconds.",
                        "/workflows/{$wi}/steps/{$si}/timeout",
                    );
                }
            }
        }
    }

    public function code(): string
    {
        return 'step.timeout_requires_11';
    }
}
