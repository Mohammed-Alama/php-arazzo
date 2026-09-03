<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

final readonly class PayloadReplacement
{
    /**
     * @param  Expression|Selector|scalar|array<mixed>|null  $value
     * @param  'jsonpointer'|'jsonpath'|'xpath'|array<array-key,mixed>|null  $targetSelectorType  simple enum or Expression Type Object
     */
    public function __construct(
        public string $target,
        public mixed $value,
        public mixed $targetSelectorType = null,
    ) {}
}
