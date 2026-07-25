<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\ParameterIn;

final readonly class Parameter
{
    /**
     * @param Expression|Selector|scalar|array<mixed>|null $value
     */
    public function __construct(
        public string $name,
        public ?ParameterIn $in,
        public mixed $value,
    ) {
    }
}
