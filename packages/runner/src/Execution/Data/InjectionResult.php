<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Psr\Http\Message\RequestInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final readonly class InjectionResult
{
    public function __construct(
        public RequestInterface $request,
        public ?string $key = null,
        public ?string $header = null,
    ) {}
}
