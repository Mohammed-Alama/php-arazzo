<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Throwable;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
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
