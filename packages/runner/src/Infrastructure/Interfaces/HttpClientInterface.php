<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Infrastructure\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface HttpClientInterface
{
    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface;
}
