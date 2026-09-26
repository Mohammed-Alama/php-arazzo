<?php

declare(strict_types=1);

namespace Alama\Arazzo\Document\Validator;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Document\Validator\Data\SymbolTable;
use Alama\Arazzo\Document\Validator\Data\ValidationResult;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class Validator
{
    public function __construct(
        private readonly RuleSet $rules,
    ) {}

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
