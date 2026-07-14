<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression;

final readonly class StepSymbols
{
    /** @param array<string,true> $outputs */
    public function __construct(public array $outputs, public int $index)
    {
    }
}
