<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Psr\Http\Message\RequestInterface;

final readonly class InjectionResult
{
    public function __construct(
        public RequestInterface $request,
        public ?string $key = null,
        public ?string $header = null,
    ) {
    }
}
