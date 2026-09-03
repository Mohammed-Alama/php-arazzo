<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;
use Alama\Arazzo\Expression\SymbolTable;

final class ExtensionsXPrefixRule implements Rule
{
    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
        foreach ($doc->specificationExtensions as $k => $_) {
            if (!str_starts_with((string) $k, 'x-')) {
                $errors->warning($this->code(), "Specification extension '{$k}' must start with 'x-'.", '/'.$k);
            }
        }
    }

    public function code(): string
    {
        return 'extensions.x_prefix';
    }
}
