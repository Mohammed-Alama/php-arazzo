<?php

declare(strict_types=1);

use Alama\Arazzo\Exceptions\UnsupportedSerializationStyleException;
use Alama\Arazzo\Execution\ParameterSerializer;

it('serializes simple style', function () {
    expect(ParameterSerializer::serializeValue('color', 'blue', 'simple', false, 'path'))->toBe('blue');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'simple', false, 'path'))->toBe('blue,black,brown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'simple', true, 'path'))->toBe('blue,black,brown');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'simple', false, 'path'))->toBe('R,100,G,200,B,150');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'simple', true, 'path'))->toBe('R=100,G=200,B=150');
});

it('serializes label style', function () {
    expect(ParameterSerializer::serializeValue('color', 'blue', 'label', false, 'path'))->toBe('.blue');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'label', false, 'path'))->toBe('.blue,black,brown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'label', true, 'path'))->toBe('.blue.black.brown');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'label', false, 'path'))->toBe('.R,100,G,200,B,150');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'label', true, 'path'))->toBe('.R=100.G=200.B=150');
});

it('serializes matrix style', function () {
    expect(ParameterSerializer::serializeValue('color', 'blue', 'matrix', false, 'path'))->toBe(';color=blue');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'matrix', false, 'path'))->toBe(';color=blue,black,brown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'matrix', true, 'path'))->toBe(';color=blue;color=black;color=brown');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'matrix', false, 'path'))->toBe(';color=R,100,G,200,B,150');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'matrix', true, 'path'))->toBe(';R=100;G=200;B=150');
});

it('serializes form style', function () {
    expect(ParameterSerializer::serializeValue('color', 'blue', 'form', false, 'query'))->toBe('color=blue');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'form', false, 'query'))->toBe('color=blue,black,brown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'form', true, 'query'))->toBe('color=blue&color=black&color=brown');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'form', false, 'query'))->toBe('color=R,100,G,200,B,150');
    expect(ParameterSerializer::serializeValue('color', ['R' => '100', 'G' => '200', 'B' => '150'], 'form', true, 'query'))->toBe('R=100&G=200&B=150');
});

it('serializes spaceDelimited style', function () {
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'spaceDelimited', false, 'query'))->toBe('color=blue+black+brown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'spaceDelimited', true, 'query'))->toBe('color=blue&color=black&color=brown');
});

it('serializes pipeDelimited style', function () {
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'pipeDelimited', false, 'query'))->toBe('color=blue%7Cblack%7Cbrown');
    expect(ParameterSerializer::serializeValue('color', ['blue', 'black', 'brown'], 'pipeDelimited', true, 'query'))->toBe('color=blue&color=black&color=brown');
});

it('throws on unsupported style', function () {
    ParameterSerializer::serializeValue('color', 'blue', 'unknownStyle', false, 'query');
})->throws(UnsupportedSerializationStyleException::class, 'Unsupported serialization style "unknownStyle" for location "query".');

it('serializes identically across OpenAPI versions (2.0/3.0/3.1)', function (array $normalizedParams, array $payload, array $expected) {
    expect(ParameterSerializer::serialize('query', $normalizedParams, $payload))->toBe($expected);
})->with([
    'OpenAPI 2.0 (implicit style/explode)' => [
        ['color' => []],
        ['color' => ['blue', 'black', 'brown']],
        ['color' => 'color=blue&color=black&color=brown'],
    ],
    'OpenAPI 3.0 (explicit form explode false)' => [
        ['color' => ['style' => 'form', 'explode' => false]],
        ['color' => ['blue', 'black', 'brown']],
        ['color' => 'color=blue,black,brown'],
    ],
    'OpenAPI 3.1 (explicit form explode true)' => [
        ['color' => ['style' => 'form', 'explode' => true]],
        ['color' => ['blue', 'black', 'brown']],
        ['color' => 'color=blue&color=black&color=brown'],
    ],
]);

it('serializes deepObject style', function (): void {
    $params = ['filter' => ['style' => 'deepObject']];

    expect(ParameterSerializer::serialize('query', $params, [
        'filter' => ['color' => 'black', 'size' => 'L'],
    ]))->toBe([
        'filter' => 'filter[color]=black&filter[size]=L',
    ]);
});

it('serializes nested deepObject style recursively', function (): void {
    $params = ['filter' => ['style' => 'deepObject']];

    expect(ParameterSerializer::serialize('query', $params, [
        'filter' => ['range' => ['from' => 1, 'to' => 9]],
    ]))->toBe([
        'filter' => 'filter[range][from]=1&filter[range][to]=9',
    ]);
});

it('degrades deepObject scalars and lists to a plain pair', function (): void {
    $params = ['x' => ['style' => 'deepObject']];

    expect(ParameterSerializer::serialize('query', $params, ['x' => 'scalar']))
        ->toBe(['x' => 'x=scalar']);

    expect(ParameterSerializer::serialize('query', $params, ['x' => [1, 2]]))
        ->toBe(['x' => 'x=%5B1%2C2%5D']); // lists degrade to JSON
});
