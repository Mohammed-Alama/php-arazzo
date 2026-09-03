<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Data;

final readonly class StepSymbols
{
    /**
     * @param  array<string,true>  $outputs
     * @param  array<string,true>  $dependsOn
     */
    public function __construct(
        public array $outputs,
        public int $index,
        public array $dependsOn = [],
    ) {}
}
