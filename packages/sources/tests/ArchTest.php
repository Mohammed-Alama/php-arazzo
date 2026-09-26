<?php

declare(strict_types=1);

// The arch guards for this package land in D0.7, once the namespace holds
// classes. Until then this asserts the thing that actually has to be true
// for them to be reachable: the package is wired into the autoload map.
it('maps the sources namespace onto the package src directory', function (): void {
    $root = json_decode(
        (string) file_get_contents(__DIR__.'/../../../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($root['autoload']['psr-4'])->toHaveKey('Alama\\Arazzo\\Sources\\')
        ->and($root['autoload']['psr-4']['Alama\\Arazzo\\Sources\\'])->toBe('packages/sources/src/')
        ->and($root['autoload-dev']['psr-4']['Alama\\Arazzo\\Tests\\'])->toContain('packages/sources/tests')
        ->and(is_dir(__DIR__.'/../src'))->toBeTrue();
});

arch('sources does not leak runner internals')
    ->expect('Alama\Arazzo\Sources\Resolver')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console')
    ->expect('Alama\Arazzo\Sources\Normalizer')
    ->not->toUse('Alama\Arazzo\Runner\Execution')
    ->not->toUse('Alama\Arazzo\Cli\Console');
