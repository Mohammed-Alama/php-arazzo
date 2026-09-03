<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

use Alama\Arazzo\Contracts\Spec\Enum\ExpressionType;

final readonly class Selector
{
    public function __construct(
        public ?string $context,
        public string $selector,
        public ExpressionType $type,
        public ?string $version = null,
    ) {}
}
