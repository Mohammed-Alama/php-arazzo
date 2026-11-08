<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support;

use Alama\Arazzo\Expression\Ast\SelfRef;
use Alama\Arazzo\Expression\Support\AstCache;

it('stores and retrieves a parsed expression by raw text', function (): void {
    $cache = new AstCache(max: 2);
    $ast = new SelfRef();

    $cache->put('{$self}', $ast);

    expect($cache->get('{$self}'))->toBe($ast)
        ->and($cache->get('{$inputs.name}'))->toBeNull()
        ->and($cache->count())->toBe(1);
});

it('keeps one entry when the same key is stored twice', function (): void {
    $cache = new AstCache(max: 2);

    $cache->put('{$self}', new SelfRef());
    $cache->put('{$self}', new SelfRef());

    expect($cache->count())->toBe(1);
});

it('evicts the oldest entry when the capacity is exceeded', function (): void {
    $cache = new AstCache(max: 2);

    $cache->put('{$inputs.a}', new SelfRef());
    $cache->put('{$inputs.b}', new SelfRef());
    $cache->put('{$inputs.c}', new SelfRef());

    expect($cache->get('{$inputs.a}'))->toBeNull()
        ->and($cache->get('{$inputs.b}'))->not->toBeNull()
        ->and($cache->get('{$inputs.c}'))->not->toBeNull()
        ->and($cache->count())->toBe(2);
});
