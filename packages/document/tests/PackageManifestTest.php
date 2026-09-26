<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests;

it('does not declare transport or openapi dependencies', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(__DIR__.'/../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $require = array_keys($manifest['require']);

    expect($require)
        ->not->toContain('cebe/php-openapi')
        ->not->toContain('guzzlehttp/guzzle')
        ->not->toContain('psr/http-client')
        ->not->toContain('psr/http-message')
        ->not->toContain('psr/simple-cache')
        ->not->toContain('softcreatr/jsonpath');
});
