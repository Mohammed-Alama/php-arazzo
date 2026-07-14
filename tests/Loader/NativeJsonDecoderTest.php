<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Loader;

use Alama\LaravelArazzo\Loader\DecodeException;
use Alama\LaravelArazzo\Loader\NativeJsonDecoder;

it('decodes valid JSON', function (): void {
    expect((new NativeJsonDecoder())->decode('{"a":1}'))->toBe(['a' => 1]);
});

it('throws DecodeException on invalid JSON', function (): void {
    expect(fn () => (new NativeJsonDecoder())->decode('{ not json'))->toThrow(DecodeException::class);
});
