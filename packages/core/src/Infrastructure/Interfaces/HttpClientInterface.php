<?php

declare(strict_types=1);

namespace Alama\Arazzo\Infrastructure\Interfaces;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientInterface
{
    public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface;
}
