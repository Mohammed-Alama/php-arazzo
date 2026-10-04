<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\ExpressionEngine;

it('validates correct expressions', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->test('$request.header.accept'))->toBeTrue();
    expect($engine->test('{$request.header.accept}'))->toBeTrue();
    expect($engine->test('${request.header.accept}'))->toBeTrue();
});

it('rejects invalid expressions', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->test('nonsensical string'))->toBeFalse();
    expect($engine->test('foobar'))->toBeFalse();
});
