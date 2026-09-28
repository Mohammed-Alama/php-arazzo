<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Execution;

use Alama\Arazzo\Runner\Execution\TypeCaster;
use InvalidArgumentException;

it('casts to integer', function () {
    expect((new TypeCaster())->asInteger(42))->toBe(42)
        ->and((new TypeCaster())->asInteger('42'))->toBe(42)
        ->and((new TypeCaster())->asInteger(42.5))->toBe(42);
});

it('throws when casting invalid integer', function () {
    (new TypeCaster())->asInteger('abc');
})->throws(InvalidArgumentException::class);

it('casts to float', function () {
    expect((new TypeCaster())->asFloat(42.5))->toBe(42.5)
        ->and((new TypeCaster())->asFloat('42.5'))->toBe(42.5)
        ->and((new TypeCaster())->asFloat(42))->toBe(42.0);
});

it('throws when casting invalid float', function () {
    (new TypeCaster())->asFloat('abc');
})->throws(InvalidArgumentException::class);

it('casts to boolean', function () {
    expect((new TypeCaster())->asBoolean(true))->toBeTrue()
        ->and((new TypeCaster())->asBoolean(false))->toBeFalse()
        ->and((new TypeCaster())->asBoolean('true'))->toBeTrue()
        ->and((new TypeCaster())->asBoolean('false'))->toBeFalse()
        ->and((new TypeCaster())->asBoolean('TRUE'))->toBeTrue()
        ->and((new TypeCaster())->asBoolean(1))->toBeTrue()
        ->and((new TypeCaster())->asBoolean(0))->toBeFalse()
        ->and((new TypeCaster())->asBoolean('1'))->toBeTrue()
        ->and((new TypeCaster())->asBoolean('0'))->toBeFalse();
});

it('throws when casting invalid boolean', function () {
    (new TypeCaster())->asBoolean('abc');
})->throws(InvalidArgumentException::class);

it('casts to string', function () {
    expect((new TypeCaster())->asString('abc'))->toBe('abc')
        ->and((new TypeCaster())->asString(42))->toBe('42')
        ->and((new TypeCaster())->asString(42.5))->toBe('42.5')
        ->and((new TypeCaster())->asString(true))->toBe('true')
        ->and((new TypeCaster())->asString(false))->toBe('false');
});

it('throws when casting invalid string', function () {
    (new TypeCaster())->asString([]);
})->throws(InvalidArgumentException::class);

it('casts to array', function () {
    expect((new TypeCaster())->asArray([]))->toBe([])
        ->and((new TypeCaster())->asArray([1, 2]))->toBe([1, 2])
        ->and((new TypeCaster())->asArray(42))->toBe([42])
        ->and((new TypeCaster())->asArray('abc'))->toBe(['abc']);
});
