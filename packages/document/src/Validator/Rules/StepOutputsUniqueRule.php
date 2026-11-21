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
final class StepOutputsUniqueRule implements Rule
{
    /** The Arazzo spec map key pattern: ^[a-zA-Z0-9.\-_]+$ */
    private const PATTERN = '/^[a-zA-Z0-9.\-_]+$/';

    public function code(): string
    {
        return 'step.outputs_unique';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->workflows as $i => $w) {
            foreach (array_keys($w->outputs) as $key) {
                if (preg_match(self::PATTERN, $key) !== 1) {
                    $errors->error(
                        'workflow.outputs_key_pattern',
                        "output key '{$key}' must match [a-zA-Z0-9._-]+.",
                        "/workflows/{$i}/outputs/{$key}",
                    );
                }
            }

            foreach ($w->steps as $j => $s) {
                foreach (array_keys($s->io->outputs) as $key) {
                    if (preg_match(self::PATTERN, $key) !== 1) {
                        $errors->error(
                            'step.outputs_key_pattern',
                            "step output key '{$key}' must match [a-zA-Z0-9._-]+.",
                            "/workflows/{$i}/steps/{$j}/outputs/{$key}",
                        );
                    }
                }
            }
        }
    }
}
