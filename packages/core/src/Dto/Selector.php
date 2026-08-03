<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

use Alama\Arazzo\Dto\Enum\ExpressionType;

final readonly class Selector
{
    public function __construct(
        public ?string $context,
        public string $selector,
        public ExpressionType $type,
        public ?string $version = null,
    ) {
    }
}
