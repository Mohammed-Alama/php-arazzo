<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\LaravelArazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ExtensionsXPrefixRule implements Rule
{
    public function code(): string { return 'extensions.x_prefix'; }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->specificationExtensions as $k => $_) {
            if (!str_starts_with((string) $k, 'x-')) {
                $errors->warning($this->code(), "Specification extension '{$k}' must start with 'x-'.", '/' . $k);
            }
        }
    }
}
