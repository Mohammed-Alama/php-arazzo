<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

use Alama\Arazzo\Dto\Enum\ParameterIn;

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
