<?php

declare(strict_types=1);

namespace Tests\Unit\Execution;

use Alama\Arazzo\Evaluation\TypeCaster;

it('casts to integer', function (): void {
    expect(TypeCaster::asInteger('42'))->toBe(42)
        ->and(TypeCaster::asInteger(42))->toBe(42);
});

it('throws on invalid integer', function (): void {
    TypeCaster::asInteger(['array']);
})->throws(\InvalidArgumentException::class);

it('casts to string', function (): void {
    expect(TypeCaster::asString(42))->toBe('42')
        ->and(TypeCaster::asString(true))->toBe('true');
});

it('casts to array', function (): void {
    expect(TypeCaster::asArray(['a']))->toBe(['a'])
        ->and(TypeCaster::asArray('a'))->toBe(['a']);
});

it('casts to float', function (): void {
    expect(TypeCaster::asFloat('4.2'))->toBe(4.2)
        ->and(TypeCaster::asFloat(42))->toBe(42.0);
});

it('throws on invalid float', function (): void {
    TypeCaster::asFloat('not-a-number');
})->throws(\InvalidArgumentException::class);

it('casts to boolean', function (): void {
    expect(TypeCaster::asBoolean(true))->toBeTrue()
        ->and(TypeCaster::asBoolean('true'))->toBeTrue()
        ->and(TypeCaster::asBoolean('false'))->toBeFalse()
        ->and(TypeCaster::asBoolean(1))->toBeTrue()
        ->and(TypeCaster::asBoolean(0))->toBeFalse();
});

it('throws on invalid boolean', function (): void {
    TypeCaster::asBoolean('not-a-bool');
})->throws(\InvalidArgumentException::class);
