<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Loader;

use Alama\Arazzo\Dto\Enum\Format;
use Alama\Arazzo\Exceptions\LoaderException;
use Alama\Arazzo\Loader\Loader;
use Alama\Arazzo\Loader\NativeJsonDecoder;
use Alama\Arazzo\Loader\SymfonyYamlDecoder;

function makeLoader(): Loader
{
    return new Loader(new SymfonyYamlDecoder(), new NativeJsonDecoder());
}

it('loads a yaml file', function (): void {
    $raw = makeLoader()->load(__DIR__ . '/../fixtures/loader/minimal.yaml');

    expect($raw->format)->toBe(Format::Yaml)
        ->and($raw->data['arazzo'] ?? null)->toBe('1.0.0')
        ->and($raw->data['workflows'][0]['workflowId'] ?? null)->toBe('wf');
});

it('loads a json file', function (): void {
    $raw = makeLoader()->load(__DIR__ . '/../fixtures/loader/minimal.json');

    expect($raw->format)->toBe(Format::Json)
        ->and($raw->data['arazzo'] ?? null)->toBe('1.0.0');
});

it('throws when file missing', function (): void {
    makeLoader()->load('/does/not/exist.yaml');
})->throws(LoaderException::class, 'not found');

it('throws on unsupported extension', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'arz') . '.txt';
    file_put_contents($tmp, 'x');
    try {
        makeLoader()->load($tmp);
    } finally {
        @unlink($tmp);
    }
})->throws(LoaderException::class, 'Unsupported');

it('throws on decode failure', function (): void {
    makeLoader()->load(__DIR__ . '/../fixtures/loader/broken.yaml');
})->throws(LoaderException::class, 'decode');

it('throws when root is not an object', function (): void {
    makeLoader()->load(__DIR__ . '/../fixtures/loader/not-object.yaml');
})->throws(LoaderException::class, 'Root');
