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

    expect($root['autoload']['psr-4'])->toHaveKey("Alama\\Arazzo\Sources\\")
        ->and($root['autoload']['psr-4']["Alama\\Arazzo\Sources\\"])->toBe('packages/sources/src/')
        ->and($root['autoload-dev']['psr-4']["Alama\\Arazzo\Tests\Sources\\"])->toContain('packages/sources/tests')
        ->and(is_dir(__DIR__.'/../src'))->toBeTrue();
});

arch('sources does not depend on outer layers')
    ->expect("Alama\\Arazzo\Sources")
    ->not->toUse("Alama\\Arazzo\Runner")
    ->not->toUse("Alama\\Arazzo\Cli")
    // Must be Protocol\Http, not bare Protocol: PSR-4 registers no
    // Alama\Arazzo\Protocol prefix (only ...\Protocol\Http), so a bare-Protocol
    // dependency layer resolves empty and can never fail. The file sweep below
    // covers the bare prefix for real.
    ->not->toUse("Alama\\Arazzo\Protocol\Http")
    ->not->toUse("Alama\\Arazzo\Runtime")
    ->not->toUse('Illuminate');

// File sweep: the arch() rules above can only see *existing* classes, and they
// scan `Alama\Arazzo\Sources` src only — so a protocol import in a test file, or
// a reference to a not-yet-existing protocol class, would never bite. The suite's
// files are namespace-light, and root autoload maps `Alama\Arazzo\Tests\` to core,
// so a namespace expectation would scan the wrong suite — sweep the files directly.
it('keeps protocol namespaces out of the sources package', function (): void {
    $needle = 'Alama\\Arazzo\\Protocol\\';
    $violations = [];

    foreach ([__DIR__.'/../src', __DIR__] as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                $violations[] = str_replace(__DIR__.'/..'.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($violations)->toBeEmpty();
});

arch('sources may reach the model package and the transport')
    ->expect("Alama\\Arazzo\Sources")
    ->toUse("Alama\\Arazzo\Document");

arch('only the sources composition root constructs http clients')
    ->expect("Alama\\Arazzo\Sources\SourceLoader")
    ->not->toUse('GuzzleHttp');
