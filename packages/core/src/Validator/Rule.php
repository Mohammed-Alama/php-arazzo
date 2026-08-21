<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;

interface Rule
{
    public function code(): string;

    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
