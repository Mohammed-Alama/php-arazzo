<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Infrastructure\Interfaces\HttpClientInterface;
use Alama\Arazzo\Laravel\Http\Psr18HttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** PSR-7/18 HTTP ports bound to Guzzle unless the app already provides them. */
final class HttpBindings
{
    public static function register(Container $app): void
    {
        $app->bindIf(ClientInterface::class, Client::class);
        $app->bindIf(RequestFactoryInterface::class, HttpFactory::class);
        $app->bindIf(StreamFactoryInterface::class, HttpFactory::class);

        $app->singleton(HttpClientInterface::class, fn (Container $app) => new Psr18HttpClient(
            $app->make(Client::class),
        ));
    }
}
