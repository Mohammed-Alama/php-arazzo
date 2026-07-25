<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

final readonly class PayloadReplacement
{
    /**
     * @param Expression|Selector|scalar|array<mixed>|null $value
     */
    public function __construct(
        public string $target,
        public mixed $value,
    ) {
    }
}
