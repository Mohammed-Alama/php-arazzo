<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Throwable;

class StepResult
{
    /**
     * @param  array<array-key, mixed>  $outputs
     */
    public function __construct(
        public readonly string $stepId,
        public readonly bool $success,
        public readonly array $outputs = [],
        public readonly ?Throwable $error = null,
    ) {}
}
