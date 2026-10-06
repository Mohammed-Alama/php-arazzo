<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Interfaces;

use Alama\Arazzo\Contracts\Spec\OpenApiPayload;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationHandle;
use Psr\Http\Message\ResponseInterface;

/**
 * @deprecated relocated to
 * Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface
 * in arazzo-protocol-http (Phase F1). Keep this FQCN as a BC alias.
 *
 * @internal BC alias; register arazzo-protocol-http to obtain the canonical type.
 */
interface OpenApiExecutorInterface
{
    public function execute(
        OpenApiOperationHandle $operation,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
        ?float $timeoutSeconds = null,
    ): ResponseInterface;
}
