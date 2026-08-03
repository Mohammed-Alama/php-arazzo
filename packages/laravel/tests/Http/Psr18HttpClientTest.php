<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Persistence;

use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Psr18HttpClientMockClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(200);
    }
}

it('delegates sendRequest to the wrapped PSR-18 client', function (): void {
    $inner = new Psr18HttpClientMockClient();
    $adapter = new Psr18HttpClient($inner);

    $request = new Request('GET', 'http://example.com');
    $response = $adapter->sendRequest($request);

    expect($inner->lastRequest)->toBe($request);
    expect($response->getStatusCode())->toBe(200);
});
