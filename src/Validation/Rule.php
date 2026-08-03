<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;

interface Rule
{
    public function code(): string;

    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
