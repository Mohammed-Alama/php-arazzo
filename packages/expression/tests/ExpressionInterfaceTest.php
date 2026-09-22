<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\Interfaces\ExpressionInterface;

it('declares the parse/inspect seam interface')
    ->expect(interface_exists(ExpressionInterface::class))->toBeTrue();

it('is implemented by the parse-side ExpressionInspector', function (): void {
    $rc = new ReflectionClass(ExpressionInterface::class);

    expect($rc->getMethods())->toHaveCount(3)
        ->and($rc->hasMethod('parseExpression'))->toBeTrue()
        ->and($rc->hasMethod('expressionReferences'))->toBeTrue()
        ->and($rc->hasMethod('buildSymbolTable'))->toBeTrue();
});
