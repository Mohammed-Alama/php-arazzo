<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression;

use Alama\Arazzo\Expression\ExpressionEngine;

it('extracts single expression', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('{$request.header.accept}'))->toBe(['$request.header.accept']);
});

it('extracts multiple expressions from a template string', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('client_id={$inputs.clientId}&grant_type={$inputs.grantType}'))->toBe(['$inputs.clientId', '$inputs.grantType']);
});

it('returns empty array when no expressions found', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('no expressions here'))->toBe([]);
});

it('extracts expressions with ${...} spelling', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('Hello ${inputs.name}!'))->toBe(['$inputs.name']);
});

it('extracts mixed spellings', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('Bearer ${inputs.token} and {$inputs.userId}'))->toBe(['$inputs.token', '$inputs.userId']);
});

it('extracts multiple expressions with same form', function (): void {
    $engine = new ExpressionEngine();
    expect($engine->extract('${inputs.token}/${inputs.userId}'))->toBe(['$inputs.token', '$inputs.userId']);
});
