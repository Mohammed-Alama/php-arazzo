<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Expression\Support;

use Alama\Arazzo\Expression\Support\AstCache;
use ReflectionClass;
use ReflectionProperty;

/** @internal stays out of the advertised contract; not part of the public API surface */
it('ast cache is final with no static properties', function (): void {
    $ref = new ReflectionClass(AstCache::class);

    expect($ref->isFinal())->toBeTrue();

    $staticProps = $ref->getProperties(ReflectionProperty::IS_STATIC);
    expect($staticProps)->toHaveCount(0);
});
