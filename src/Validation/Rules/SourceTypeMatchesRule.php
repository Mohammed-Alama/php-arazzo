<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

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
