<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator\Rules;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\ErrorCollector;
use Alama\Arazzo\Document\Validator\Interfaces\Rule;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ActionTypeValidRule implements Rule
{
    public function code(): string
    {
        return 'action.type_valid';
    }

    public function check(ArazzoDocument $doc, SymbolTable $symbols, ErrorCollector $errors): void {}
}
