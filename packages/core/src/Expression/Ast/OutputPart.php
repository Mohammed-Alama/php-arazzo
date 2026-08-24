<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

final readonly class OutputPart extends StepPart
{
    public function __construct(
        public string $name,
        public ?string $jsonPointer = null,
    ) {
    }
}
