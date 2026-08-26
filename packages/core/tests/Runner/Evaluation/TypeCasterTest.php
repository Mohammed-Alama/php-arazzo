<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Runner\Evaluation;

use Alama\Arazzo\Evaluation\TypeCaster;
use InvalidArgumentException;

it('casts to integer', function () {
    expect(TypeCaster::asInteger(42))->toBe(42)
        ->and(TypeCaster::asInteger('42'))->toBe(42)
        ->and(TypeCaster::asInteger(42.5))->toBe(42);
});

it('throws when casting invalid integer', function () {
    TypeCaster::asInteger('abc');
})->throws(InvalidArgumentException::class);

it('casts to float', function () {
    expect(TypeCaster::asFloat(42.5))->toBe(42.5)
        ->and(TypeCaster::asFloat('42.5'))->toBe(42.5)
        ->and(TypeCaster::asFloat(42))->toBe(42.0);
});

it('throws when casting invalid float', function () {
    TypeCaster::asFloat('abc');
})->throws(InvalidArgumentException::class);

it('casts to boolean', function () {
    expect(TypeCaster::asBoolean(true))->toBeTrue()
        ->and(TypeCaster::asBoolean(false))->toBeFalse()
        ->and(TypeCaster::asBoolean('true'))->toBeTrue()
        ->and(TypeCaster::asBoolean('false'))->toBeFalse()
        ->and(TypeCaster::asBoolean('TRUE'))->toBeTrue()
        ->and(TypeCaster::asBoolean(1))->toBeTrue()
        ->and(TypeCaster::asBoolean(0))->toBeFalse()
        ->and(TypeCaster::asBoolean('1'))->toBeTrue()
        ->and(TypeCaster::asBoolean('0'))->toBeFalse();
});

it('throws when casting invalid boolean', function () {
    TypeCaster::asBoolean('abc');
})->throws(InvalidArgumentException::class);

it('casts to string', function () {
    expect(TypeCaster::asString('abc'))->toBe('abc')
        ->and(TypeCaster::asString(42))->toBe('42')
        ->and(TypeCaster::asString(42.5))->toBe('42.5')
        ->and(TypeCaster::asString(true))->toBe('true')
        ->and(TypeCaster::asString(false))->toBe('false');
});

it('throws when casting invalid string', function () {
    TypeCaster::asString([]);
})->throws(InvalidArgumentException::class);

it('casts to array', function () {
    expect(TypeCaster::asArray([]))->toBe([])
        ->and(TypeCaster::asArray([1, 2]))->toBe([1, 2])
        ->and(TypeCaster::asArray(42))->toBe([42])
        ->and(TypeCaster::asArray('abc'))->toBe(['abc']);
});
