<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

use Alama\Arazzo\Dto\SourceDescription;
use Alama\Arazzo\Runner\Dto\OpenApiPayload;
use Psr\Http\Message\ResponseInterface;

interface OpenApiExecutorInterface
{
    public function execute(
        SourceDescription $source,
        string $operationIdOrPath,
        OpenApiPayload $payload,
        ?callable $requestInterceptor = null,
    ): ResponseInterface;
}
