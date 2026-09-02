<?php

declare(strict_types=1);

use Alama\Arazzo\Spec\Enum\ExpressionType;

it('has three canonical cases', function () {
    expect(ExpressionType::JsonPath->value)->toBe('jsonpath')
        ->and(ExpressionType::XPath->value)->toBe('xpath')
        ->and(ExpressionType::JsonPointer->value)->toBe('jsonpointer');
});

it('rejects unknown values via tryFrom', function () {
    expect(ExpressionType::tryFrom('regex'))->toBeNull();
});
