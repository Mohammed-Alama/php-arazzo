<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;
use Alama\LaravelArazzo\Validation\ErrorCollector;
use Alama\LaravelArazzo\Validation\Rule;

final class ComponentsUniqueNamesRule implements Rule
{
    public function code(): string
    {
        return 'components.unique_names';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void
    {
    }
}
