<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

/**
 * Enum enforcement happens at parse time; this rule exists so that a stable
 * `source.type_matches` code is reserved and so future non-parser-time checks
 * (e.g. "type: arazzo yet url points to an OpenAPI file") can land here.
 */
final class SourceTypeMatchesRule implements Rule
{
    public function code(): string
    {
        return 'source.type_matches';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        // No-op in v1 — the enum guarantees correctness.
    }
}
