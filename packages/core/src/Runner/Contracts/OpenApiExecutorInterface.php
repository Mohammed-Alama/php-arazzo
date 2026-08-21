<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Alama\Arazzo\Runner\Resolver\ResolvedOperation;
use Psr\Http\Message\ResponseInterface;

interface OpenApiExecutorInterface
{
    public function execute(
        ResolvedOperation $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
    ): ResponseInterface;
}
