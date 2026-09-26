<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\Data\ExpressionReference;
use Alama\Arazzo\Expression\Enum\ReferenceKind;
use Alama\Arazzo\Expression\Exceptions\ExpressionSyntaxException;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;

it('implements ExpressionEngineInterface', function () {
    $engine = new ExpressionEngine();

    expect($engine)->toBeInstanceOf(ExpressionEngineInterface::class);
});

it('parses valid and invalid expressions', function () {
    $engine = new ExpressionEngine();

    expect($engine->parseExpression('$inputs.userId'))
        ->toBeNull()
        ->and($engine->parseExpression('invalid expr'))
        ->toBeInstanceOf(ExpressionSyntaxException::class);
});

it('projects expression references statically', function () {
    $engine = new ExpressionEngine();

    $ref = $engine->expressionReferences('$inputs.userId');
    expect($ref)->toBeInstanceOf(ExpressionReference::class)
        ->and($ref->kind)->toBe(ReferenceKind::Input)
        ->and($ref->target)->toBe('userId')
        ->and($engine->expressionReferences('bad'))->toBeNull();
});
