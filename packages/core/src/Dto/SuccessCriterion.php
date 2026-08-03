<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto;

use Alama\Arazzo\Dto\Enum\CriterionType;

final readonly class SuccessCriterion
{
    public function __construct(
        public ?string $context,
        public string $condition,
        public ?CriterionType $type,
        public ?string $version = null,
    ) {
    }
}
