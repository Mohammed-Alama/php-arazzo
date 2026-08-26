<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Policy;

interface BackoffCalculatorInterface
{
    public function calculate(float $baseDelay, int $attempt, float $multiplier): int;
}
