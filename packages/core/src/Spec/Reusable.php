<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class Reusable
{
    /**
     * @param  Expression|Selector|scalar|array<mixed>|null  $value
     */
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {}
}
