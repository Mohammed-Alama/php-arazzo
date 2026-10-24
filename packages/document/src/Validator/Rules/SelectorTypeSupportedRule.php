<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Selector;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
            $this->checkParameters($wf->parameters, "/workflows/{$wi}/parameters", $errors);
            foreach ($wf->steps as $si => $step) {
                $this->checkStep($step, "/workflows/{$wi}/steps/{$si}", $errors);
            }
            $this->checkValues($wf->outputs, "/workflows/{$wi}/outputs", $errors);
        }

        foreach ($doc->components->parameters as $name => $p) {
            if ($p->value instanceof Selector) {
                $this->validateSelector($p->value, $errors, "/components/parameters/{$name}/value");
            }
        }
    }

    /**
     * @param  array<int|string,Parameter|Reusable>  $parameters
     */
    private function checkParameters(array $parameters, string $basePath, ErrorCollector $errors): void
    {
        foreach ($parameters as $pi => $p) {
            if ($p instanceof Reusable) {
                continue;
            }

            if ($p->value instanceof Selector) {
                $this->validateSelector($p->value, $errors, "{$basePath}/{$pi}/value");
            }
        }
    }

    private function checkStep(Step $step, string $basePath, ErrorCollector $errors): void
    {
        $this->checkParameters($step->io->parameters, "{$basePath}/parameters", $errors);

        if ($step->io->requestBody !== null) {
            foreach ($step->io->requestBody->replacements as $ri => $r) {
                if ($r->value instanceof Selector) {
                    $this->validateSelector($r->value, $errors, "{$basePath}/requestBody/replacements/{$ri}/value");
                }
            }
        }

        $this->checkValues($step->io->outputs, "{$basePath}/outputs", $errors);
    }

    /**
     * @param  array<array-key,mixed>  $values
     */
    private function checkValues(array $values, string $basePath, ErrorCollector $errors): void
    {
        foreach ($values as $name => $value) {
            if ($value instanceof Selector) {
                $this->validateSelector($value, $errors, "{$basePath}/{$name}");
            }
        }
    }

    private function validateSelector(Selector $s, ErrorCollector $errors, string $pointer): void
    {
        if ($s->version === null) {
            return; // No pinned version = implementation default; allowed.
        }

        $allowed = self::KNOWN_VERSIONS[$s->type->value];
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
