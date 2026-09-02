<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator\Interfaces;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Validator\ErrorCollector;

interface Rule
{
    public function code(): string;

    public function check(
        ArazzoDocument $doc,
        SymbolTable $symbols,
        ErrorCollector $errors,
    ): void;
}
