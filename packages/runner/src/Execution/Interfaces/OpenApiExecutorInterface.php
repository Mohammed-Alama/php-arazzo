<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Interfaces;

use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Document\Normalizer\ResolvedOperation;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface OpenApiExecutorInterface
{
    public function execute(
        ResolvedOperation $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface;
}
