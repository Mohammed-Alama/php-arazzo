<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution\Fetchers;

use Alama\Arazzo\Resolution\Exceptions\SourceFetchException;
use Alama\Arazzo\Resolution\Fetchers\HttpFetcher;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

it('fetches an http url', function (): void {
    $client = \Mockery::mock(ClientInterface::class);
    $reqFactory = \Mockery::mock(RequestFactoryInterface::class);
    $request = \Mockery::mock(RequestInterface::class);
    $response = \Mockery::mock(ResponseInterface::class);

    $stream = \Mockery::mock(\Psr\Http\Message\StreamInterface::class);
    $stream->shouldReceive('__toString')->andReturn('fetched content');
    $reqFactory->shouldReceive('createRequest')->with('GET', 'http://example.com/spec.yaml')->andReturn($request);
    $client->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($stream);

    $fetcher = new HttpFetcher($client, $reqFactory);

    expect($fetcher->fetch('http://example.com/spec.yaml', ''))->toBe('fetched content');
});

it('throws SourceFetchException on http error', function (): void {
    $client = \Mockery::mock(ClientInterface::class);
    $reqFactory = \Mockery::mock(RequestFactoryInterface::class);
    $request = \Mockery::mock(RequestInterface::class);
    $response = \Mockery::mock(ResponseInterface::class);

    $reqFactory->shouldReceive('createRequest')->with('GET', 'http://example.com/spec.yaml')->andReturn($request);
    $client->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(404);

    $fetcher = new HttpFetcher($client, $reqFactory);

    expect(fn () => $fetcher->fetch('http://example.com/spec.yaml', ''))
        ->toThrow(SourceFetchException::class, 'HTTP request failed for http://example.com/spec.yaml: 404');
});
