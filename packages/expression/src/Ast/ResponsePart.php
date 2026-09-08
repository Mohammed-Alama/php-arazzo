<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Ast;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class ResponsePart extends StepPart
{
    public function __construct(
        public ?string $httpPart,
        public ?string $headerName,
        public ?string $jsonPointer,
    ) {}
}
