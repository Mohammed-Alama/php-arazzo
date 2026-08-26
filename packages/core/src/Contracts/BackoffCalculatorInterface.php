<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

interface BackoffCalculatorInterface
{
    public function calculate(float $baseDelay, int $attempt, float $multiplier): int;
}
