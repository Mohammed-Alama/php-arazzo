<?php

declare(strict_types=1);

use Alama\Arazzo\Runner\Exceptions\UnsupportedSerializationStyleException;
use Alama\Arazzo\Runner\ParameterSerializer;

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
    ParameterSerializer::serializeValue('color', 'blue', 'deepObject', false, 'query');
})->throws(UnsupportedSerializationStyleException::class, 'Unsupported serialization style "deepObject" for location "query".');
