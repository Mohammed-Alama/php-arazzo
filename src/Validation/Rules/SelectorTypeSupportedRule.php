<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Dto\Enum\ExpressionType;
use Alama\LaravelArazzo\Dto\Enum\SpecVersion;
use Alama\LaravelArazzo\Dto\Selector;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class SelectorTypeSupportedRule implements Rule
{
    private const KNOWN_VERSIONS = [
        'jsonpath'    => ['rfc9535'],
        'xpath'       => ['xpath-10', 'xpath-20', 'xpath-30', 'xpath-31'],
        'jsonpointer' => ['rfc6901'],
    ];

    public function code(): string
    {
        return 'selector.type_supported';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->steps as $si => $step) {
                foreach ($step->outputs as $name => $value) {
                    if ($value instanceof Selector) {
                        $this->validateSelector(
                            $value, $errors,
                            "/workflows/{$wi}/steps/{$si}/outputs/{$name}",
                        );
                    }
                }
            }
            foreach ($wf->outputs as $name => $value) {
                if ($value instanceof Selector) {
                    $this->validateSelector(
                        $value, $errors,
                        "/workflows/{$wi}/outputs/{$name}",
                    );
                }
            }
        }
    }

    private function validateSelector(Selector $s, ErrorCollector $errors, string $pointer): void
    {
        if ($s->version === null) {
            return; // No pinned version = implementation default; allowed.
        }

        $allowed = self::KNOWN_VERSIONS[$s->type->value] ?? [];
        if (!in_array($s->version, $allowed, true)) {
            $errors->error(
                $this->code(),
                "Unsupported {$s->type->value} version '{$s->version}' at {$pointer}; expected one of: " . implode(', ', $allowed),
                $pointer,
            );
        }
    }
}
