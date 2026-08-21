<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

final readonly class Reusable
{
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {
    }
}
