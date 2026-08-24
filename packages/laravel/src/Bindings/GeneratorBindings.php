<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Generator\ArazzoGenerator;
use Alama\Arazzo\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Generator\Contracts\AiClientInterface;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/** AI generator stack (dormant license gate lives elsewhere). */
final class GeneratorBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(AiClientInterface::class, function (Container $app) {
            return new OpenAiClient(
                $app->make(ClientInterface::class),
                $app->make(RequestFactoryInterface::class),
                $app->make(StreamFactoryInterface::class),
                ConfigValue::string(config('arazzo.openai.api_key', ''), ''),
                ConfigValue::string(config('arazzo.openai.model', 'gpt-4o'), 'gpt-4o'),
            );
        });

        $app->singleton(ArazzoGenerator::class, function (Container $app) {
            return new ArazzoGenerator($app->make(AiClientInterface::class));
        });
    }
}
