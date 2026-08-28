<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

use Alama\Arazzo\Expression\Expression;
use Alama\Arazzo\Expression\Selector;

final readonly class Reusable
{
    /**
     * @param  Expression|Selector|scalar|array<mixed>|null  $value
     */
    public function __construct(
        public string $reference,
        public mixed $value = null,
    ) {}
}
