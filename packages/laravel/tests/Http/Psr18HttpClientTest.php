<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests\Persistence;

use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('delegates sendRequest to the wrapped client when no timeout is given', function (): void {
    $handler = new MockHandler([new Response(200)]);
    $adapter = new Psr18HttpClient(new Client(['handler' => HandlerStack::create($handler)]));

    $request = new Request('GET', 'http://example.com');
    $response = $adapter->sendRequest($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($handler->getLastRequest()->getMethod())->toBe('GET')
        ->and((string) $handler->getLastRequest()->getUri())->toBe('http://example.com');
});

it('passes the timeout option through to guzzle when given', function (): void {
    $handler = new MockHandler([new Response(202)]);
    $client = new Client(['handler' => HandlerStack::create($handler)]);
    $adapter = new Psr18HttpClient($client);

    $request = new Request('POST', 'http://broker.local/publish', ['Content-Type' => 'application/json'], '{"x":1}');
    $response = $adapter->sendRequest($request, 4.5);

    expect($response->getStatusCode())->toBe(202);

    $sent = $handler->getLastRequest();
    expect($sent->getMethod())->toBe('POST')
        ->and($sent->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $sent->getBody())->toBe('{"x":1}');
});
