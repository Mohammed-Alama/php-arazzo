<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto;

use Alama\LaravelArazzo\Dto\Enum\ExpressionType;

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
