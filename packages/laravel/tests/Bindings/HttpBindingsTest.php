<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Bindings\HttpBindings;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use Alama\Arazzo\Runner\Execution\Contracts\HttpClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('binds guzzle as the default psr-18 client and factories', function (): void {
    expect(app(ClientInterface::class))->toBeInstanceOf(Client::class)
        ->and(app(RequestFactoryInterface::class))->toBeInstanceOf(HttpFactory::class)
        ->and(app(StreamFactoryInterface::class))->toBeInstanceOf(HttpFactory::class);
});

it('bindIf keeps an application-provided client instead of the guzzle default', function (): void {
    $custom = new class() implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            throw new LogicException('not used');
        }
    };

    app()->instance(ClientInterface::class, $custom);
    HttpBindings::register($this->app);

    expect(app(ClientInterface::class))->toBe($custom);
});

it('wraps the bound guzzle client in one shared Psr18HttpClient singleton', function (): void {
    $first = app(HttpClientInterface::class);
    $second = app(HttpClientInterface::class);

    expect($first)->toBeInstanceOf(Psr18HttpClient::class)
        ->and($first)->toBe($second);

    // The wrapper holds a real Guzzle client (fresh per resolution; only the
    // interface->concrete pair is bound).
    $inner = Closure::bind(fn ($h) => $h->client, null, Psr18HttpClient::class)($first);
    expect($inner)->toBeInstanceOf(Client::class);
});
