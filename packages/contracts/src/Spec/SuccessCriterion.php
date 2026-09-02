<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Spec\Enum\CriterionType;

final readonly class SuccessCriterion
{
    public function __construct(
        public ?string $context,
        public string $condition,
        public ?CriterionType $type,
        public ?string $version = null,
    ) {}
}
