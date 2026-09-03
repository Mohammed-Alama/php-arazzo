<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\SpecVersion;

it('accepts 1.0.x', function (string $raw) {
    expect(SpecVersion::fromRaw($raw))->toBe(SpecVersion::V1_0);
})->with(['1.0.0', '1.0.1', '1.0.99']);

it('accepts 1.1.x', function (string $raw) {
    expect(SpecVersion::fromRaw($raw))->toBe(SpecVersion::V1_1);
})->with(['1.1.0', '1.1.5', '1.1.99']);

it('rejects unsupported versions', function (string $raw) {
    SpecVersion::fromRaw($raw);
})->throws(InvalidArgumentException::class)->with(['0.9.0', '1.2.0', '2.0.0', 'abc', '', '1.0', '1']);

it('exposes cases as canonical strings', function () {
    expect(SpecVersion::V1_0->value)->toBe('1.0.0')
        ->and(SpecVersion::V1_1->value)->toBe('1.1.0');
});
