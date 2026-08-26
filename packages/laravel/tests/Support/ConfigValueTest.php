<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Support\ConfigValue;

it('narrows ints: passes ints, coerces numerics, defaults otherwise', function (mixed $value, int $default, int $expected) {
    expect(ConfigValue::int($value, $default))->toBe($expected);
})->with([
    'int passthrough' => [42, 1, 42],
    'numeric string' => ['7', 1, 7],
    'float string truncates' => ['3.9', 1, 3],
    'null falls back' => [null, 5, 5],
    'array falls back' => [[], 5, 5],
    'bool falls back' => [true, 5, 5],
]);

it('narrows floats: accepts numerics, defaults otherwise', function (mixed $value, float $default, float $expected) {
    expect(ConfigValue::float($value, $default))->toBe($expected);
})->with([
    'float passthrough' => [2.5, 1.0, 2.5],
    'int widens' => [2, 1.0, 2.0],
    'numeric string' => ['1.5', 1.0, 1.5],
    'string junk falls back' => ['fast', 0.75, 0.75],
    'null falls back' => [null, 0.75, 0.75],
]);

it('narrows strings: passes strings, stringifies scalars, defaults otherwise', function (mixed $value, string $default, string $expected) {
    expect(ConfigValue::string($value, $default))->toBe($expected);
})->with([
    'string passthrough' => ['table_a', 'x', 'table_a'],
    'int coerces' => [10, 'x', '10'],
    'float coerces' => [1.25, 'x', '1.25'],
    'bool coerces' => [true, 'x', '1'],
    'null falls back' => [null, 'fallback', 'fallback'],
    'array falls back' => [['a'], 'fallback', 'fallback'],
]);

it('narrows bools: passes bools, truthy-coerces scalars, defaults otherwise', function (mixed $value, bool $default, bool $expected) {
    expect(ConfigValue::bool($value, $default))->toBe($expected);
})->with([
    'true passthrough' => [true, false, true],
    'false passthrough' => [false, true, false],
    'int 1 is truthy' => [1, false, true],
    'int 0 is falsy' => [0, true, false],
    'empty string is falsy' => ['', true, false],
    'non-empty string is truthy' => ['yes', false, true],
    'null falls back' => [null, true, true],
    'array falls back' => [[1], false, false],
]);
