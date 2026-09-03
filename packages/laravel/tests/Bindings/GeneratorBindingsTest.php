<?php

declare(strict_types=1);

use Alama\Arazzo\Cli\Generator\ArazzoGenerator;
use Alama\Arazzo\Cli\Generator\Clients\OpenAiClient;
use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Laravel\Bindings\GeneratorBindings;

function generatorClientProp(object $client, string $name): mixed
{
    $prop = new ReflectionProperty($client, $name);
    $prop->setAccessible(true);

    return $prop->getValue($client);
}

it('binds AiClientInterface to an OpenAiClient', function (): void {
    GeneratorBindings::register($this->app);

    expect(app(AiClientInterface::class))->toBeInstanceOf(OpenAiClient::class);
});

it('feeds the openai api_key config into the client', function (): void {
    config()->set('arazzo.openai.api_key', 'sk-test-123');
    GeneratorBindings::register($this->app);

    expect(generatorClientProp(app(AiClientInterface::class), 'apiKey'))->toBe('sk-test-123');
});

it('falls back to an empty api key when config is missing', function (): void {
    config()->set('arazzo.openai.api_key', null);
    GeneratorBindings::register($this->app);

    expect(generatorClientProp(app(AiClientInterface::class), 'apiKey'))->toBe('');
});

it('binds the ArazzoGenerator', function (): void {
    GeneratorBindings::register($this->app);

    expect(app(ArazzoGenerator::class))->toBeInstanceOf(ArazzoGenerator::class);
});

it('builds the generator around the bound ai client', function (): void {
    GeneratorBindings::register($this->app);

    $generator = app(ArazzoGenerator::class);

    expect(generatorClientProp($generator, 'aiClient'))->toBe(app(AiClientInterface::class));
});
