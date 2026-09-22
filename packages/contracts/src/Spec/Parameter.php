<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\ParameterIn;
use Alama\Arazzo\Contracts\Spec\Enum\ValueMode;

final readonly class Parameter
{
    /**
     * @param  Expression|Selector|scalar|array<mixed>|null  $value
     */
    public function __construct(
        public string $name,
        public ?ParameterIn $in,
        public mixed $value,
        public ?ValueMode $valueMode = null,
    ) {}
}
