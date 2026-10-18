<?php

declare(strict_types=1);

use Alama\Arazzo\Laravel\Support\Payload;

it('narrows records: keeps maps, drops non-string keys, empties otherwise', function (mixed $value, array $expected) {
    expect(Payload::record($value))->toBe($expected);
})->with([
    'flat map passes through' => [['a' => 1, 'b' => 'two'], ['a' => 1, 'b' => 'two']],
    'numeric keys dropped' => [[0 => 'a', 'b' => 'two'], ['b' => 'two']],
    'nested arrays preserved' => [['a' => ['b' => [1, 2]]], ['a' => ['b' => [1, 2]]]],
    'null becomes empty' => [null, []],
    'scalar becomes empty' => ['not-a-record', []],
    'empty array stays empty' => [[], []],
]);

it('reads records out of a container: skips non-array items and unusable keys', function (mixed $container, array $expected) {
    expect(Payload::records($container, 'nodes'))->toBe($expected);
})->with([
    'records pass through' => [['nodes' => [['id' => 'n1']]], [['id' => 'n1']]],
    'non-array items skipped' => [['nodes' => [['id' => 'n1'], 'junk', 42, null]], [['id' => 'n1']]],
    'missing key' => [['edges' => []], []],
    'non-array container' => ['junk', []],
    'non-array value' => [['nodes' => 'junk'], []],
    'non-string keys dropped' => [['nodes' => [[0 => 'kept', 'id' => 'n1']]], [['id' => 'n1']]],
]);

it('narrows text: passes strings, stringifies scalars, defaults otherwise', function (mixed $value, string $expected) {
    expect(Payload::text($value))->toBe($expected);
})->with([
    'string passthrough' => ['GET', 'GET'],
    'empty string stays empty' => ['', ''],
    'int coerces' => [200, '200'],
    'float coerces' => [1.5, '1.5'],
    'bool coerces' => [true, '1'],
    'null falls back' => [null, ''],
    'array falls back' => [['a'], ''],
]);

it('narrows text to a caller-supplied default', function (): void {
    expect(Payload::text(null, 'fallback'))->toBe('fallback')
        ->and(Payload::text(['nested'], 'fallback'))->toBe('fallback')
        ->and(Payload::text('value', 'fallback'))->toBe('value');
});
