<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec;

final readonly class Step
{
    public function __construct(
        public string $stepId,
        public ?string $description,
        public StepTarget $target,
        public StepFlow $flow,
        public StepIo $io,
    ) {}
}
