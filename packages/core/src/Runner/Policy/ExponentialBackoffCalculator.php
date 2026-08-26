<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Policy;

final class ExponentialBackoffCalculator implements BackoffCalculatorInterface
{
    public function calculate(float $baseDelay, int $attempt, float $multiplier): int
    {
        if ($baseDelay <= 0) {
            return 0;
        }

        $scaled = $baseDelay * ($multiplier ** max(0, $attempt - 1));

        return max(0, (int) ceil($scaled));
    }
}
