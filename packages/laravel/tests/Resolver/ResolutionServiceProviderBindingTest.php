<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Resolver\DefaultSourceResolver;
use Alama\Arazzo\Resolver\SourceResolver;

it('resolves SourceResolver from the container as a DefaultSourceResolver', function (): void {
    $resolver = $this->app->make(SourceResolver::class);

    expect($resolver)->toBeInstanceOf(DefaultSourceResolver::class);
});
