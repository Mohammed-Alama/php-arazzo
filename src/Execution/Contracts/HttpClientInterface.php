<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
