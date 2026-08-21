<?php

declare(strict_types=1);

namespace Alama\Arazzo\Validator;

use Alama\Arazzo\Expression\SymbolTable;
use Alama\Arazzo\Spec\ArazzoDocument;

final class Validator
{
    public function __construct(private readonly RuleSet $rules)
    {
    }

    public function validate(ArazzoDocument $doc): ValidationResult
    {
        $symbols = SymbolTable::build($doc);
        $collector = new ErrorCollector();

        foreach ($this->rules->activeRules() as $rule) {
            $rule->check($doc, $symbols, $collector);
        }

        return new ValidationResult($doc, $collector->errors(), $collector->warnings());
    }
}
