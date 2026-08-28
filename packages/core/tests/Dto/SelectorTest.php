<?php

declare(strict_types=1);

use Alama\Arazzo\Expression\Enum\ExpressionType;
use Alama\Arazzo\Expression\Selector;

it('constructs with all fields', function () {
    $s = new Selector('$response.body', '$.data.id', ExpressionType::JsonPath, 'rfc9535');
    expect($s->context)->toBe('$response.body')
        ->and($s->selector)->toBe('$.data.id')
        ->and($s->type)->toBe(ExpressionType::JsonPath)
        ->and($s->version)->toBe('rfc9535');
});

it('allows null context and null version', function () {
    $s = new Selector(null, '/foo/0', ExpressionType::JsonPointer);
    expect($s->context)->toBeNull()->and($s->version)->toBeNull();
});
