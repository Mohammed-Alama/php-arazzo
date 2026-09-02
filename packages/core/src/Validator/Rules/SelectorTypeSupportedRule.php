<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\SpecVersion;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\Selector;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Interfaces\Rule;

final class SelectorTypeSupportedRule implements Rule
{
    private const KNOWN_VERSIONS = [
        'jsonpath' => ['rfc9535'],
        'xpath' => ['xpath-10', 'xpath-20', 'xpath-30', 'xpath-31'],
        'jsonpointer' => ['rfc6901'],
    ];

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        if ($doc->specVersion === SpecVersion::V1_0) {
            return;
        }

        foreach ($doc->workflows as $wi => $wf) {
            foreach ($wf->parameters as $pi => $p) {
                if ($p instanceof Reusable) {
                    continue;
                }

                if ($p->value instanceof Selector) {
                    $this->validateSelector($p->value, $errors, "/workflows/{$wi}/parameters/{$pi}/value");
                }
            }
            foreach ($wf->steps as $si => $step) {
                foreach ($step->parameters as $pi => $p) {
                    if ($p instanceof Reusable) {
                        continue;
                    }

                    if ($p->value instanceof Selector) {
                        $this->validateSelector($p->value, $errors, "/workflows/{$wi}/steps/{$si}/parameters/{$pi}/value");
                    }
                }
                if ($step->requestBody !== null) {
                    foreach ($step->requestBody->replacements as $ri => $r) {
                        if ($r->value instanceof Selector) {
                            $this->validateSelector($r->value, $errors, "/workflows/{$wi}/steps/{$si}/requestBody/replacements/{$ri}/value");
                        }
                    }
                }
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
        foreach ($doc->components->parameters as $name => $p) {
            if ($p->value instanceof Selector) {
                $this->validateSelector($p->value, $errors, "/components/parameters/{$name}/value");
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
                "Unsupported {$s->type->value} version '{$s->version}' at {$pointer}; expected one of: ".implode(', ', $allowed),
                $pointer,
            );
        }
    }

    public function code(): string
    {
        return 'selector.type_supported';
    }
}
