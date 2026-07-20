<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Dto;

use Throwable;

class StepResult
{
    public function __construct(
        public readonly string $stepId,
        public readonly bool $success,
        public readonly array $outputs = [],
        public readonly ?Throwable $error = null
    ) {}
}
