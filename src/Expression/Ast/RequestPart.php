<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Expression\Ast;

final readonly class RequestPart extends StepPart
{
    public function __construct(
        public ?string $httpPart,
        public ?string $headerName,
        public ?string $jsonPointer,
    ) {
    }
}
