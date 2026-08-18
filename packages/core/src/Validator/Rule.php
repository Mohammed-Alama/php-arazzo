<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\Arazzo\Expression\SymbolTable;

interface Rule
{
    public function code(): string;

    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
