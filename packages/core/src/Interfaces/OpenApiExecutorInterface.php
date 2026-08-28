<?php

declare(strict_types=1);

namespace Alama\Arazzo\Interfaces;

use Alama\Arazzo\Normalizer\ResolvedOperation;
use Alama\Arazzo\Spec\OpenApiPayload;
use Psr\Http\Message\ResponseInterface;

interface OpenApiExecutorInterface
{
    public function execute(
        ResolvedOperation $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface;
}
