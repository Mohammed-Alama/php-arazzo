<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validation\Rules;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Validation\ErrorCollector;
use Alama\Arazzo\Validation\Rule;

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
