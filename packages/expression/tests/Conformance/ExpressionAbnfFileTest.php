<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Tests\Conformance;

use Alama\Arazzo\Tests\Expression\Support\Abnf\AbnfGrammar;

/** @internal stays out of the advertised contract; not part of the public API surface */
it('abnf file has provenance header', function (string $path, string $version): void {
    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    expect($contents)->toContain('Spec version: '.$version);
    expect($contents)->toContain('Source: ');
    expect($contents)->toContain('Retrieved: ');
    expect($contents)->toContain('Arazzo Specification');
})->with([
    '1.0.1' => [
        __DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.0.1.abnf',
        '1.0.1',
    ],
    '1.1.0' => [
        __DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.1.0.abnf',
        '1.1.0',
    ],
]);

it('abnf file parses with no undefined rule references', function (string $path): void {
    $grammar = AbnfGrammar::parse(file_get_contents($path));
    $undefined = $grammar->undefinedRules();
    expect($undefined)->toEqual([]);
})->with([
    '1.0.1' => [
        __DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.0.1.abnf',
    ],
    '1.1.0' => [
        __DIR__.'/../../resources/grammar/arazzo-runtime-expression-1.1.0.abnf',
    ],
]);
