<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Validation;

use Alama\Arazzo\Dto\ArazzoDocument;
use Alama\LaravelArazzo\Expression\SymbolTable;

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
