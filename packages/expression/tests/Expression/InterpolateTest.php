<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\Data\InterpolationOptions;
use Alama\Arazzo\Expression\ExpressionEngine;

it('interpolates single expression', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('Hello {$inputs.name}!', fn (string $expr) => 'world');
    expect($result)->toBe('Hello world!');
});

it('interpolates multiple expressions from lookup table', function (): void {
    $engine = new ExpressionEngine();
    $values = ['$inputs.clientId' => 'abc', '$inputs.grantType' => 'authorization_code'];
    $result = $engine->interpolate(
        'client_id={$inputs.clientId}&grant_type={$inputs.grantType}',
        fn (string $expr) => $values[$expr] ?? '',
    );
    expect($result)->toBe('client_id=abc&grant_type=authorization_code');
});

it('returns string unchanged when no expressions found', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('no expressions here', fn (string $expr) => 'X');
    expect($result)->toBe('no expressions here');
});

it('interpolates ${...} spelling', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('Hello ${inputs.name}!', fn (string $expr) => 'world');
    expect($result)->toBe('Hello world!');
});

it('interpolates mixed spellings', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('Bearer ${inputs.token} and {$inputs.userId}', fn (string $expr) => match ($expr) {
        '$inputs.token' => 'abc1234',
        '$inputs.userId' => '42',
        default => '',
    });
    expect($result)->toBe('Bearer abc1234 and 42');
});

it('converts null and undefined-like to empty string by default', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('Value: {$inputs.missing}', fn (string $expr) => null);
    expect($result)->toBe('Value: ');
});

it('json encodes objects by default', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('{$request.body}', fn (string $expr) => ['id' => 1]);
    expect($result)->toBe('{"id":1}');
});

it('supports custom stringify', function (): void {
    $engine = new ExpressionEngine();
    $result = $engine->interpolate('{$request.body}', fn (string $expr) => ['id' => 1], [
        'stringify' => fn (mixed $value) => json_encode($value, JSON_PRETTY_PRINT),
    ]);
    expect($result)->toBe("{\n    \"id\": 1\n}");
});

it('interpolateWithOptions works with InterpolationOptions object', function (): void {
    $engine = new ExpressionEngine();
    $opts = new InterpolationOptions(stringify: fn (mixed $v) => strtoupper((string) $v));
    $result = $engine->interpolateWithOptions('Hello {$name}', fn () => 'world', $opts);
    expect($result)->toBe('Hello WORLD');
});
