<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validator\ErrorCollector;
use Alama\Arazzo\Validator\Rule;

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
