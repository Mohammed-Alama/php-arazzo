<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests\Resolution;

use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\SourceResolver;

it('resolves SourceResolver from the container as a DefaultSourceResolver', function (): void {
    $resolver = $this->app->make(SourceResolver::class);

    expect($resolver)->toBeInstanceOf(DefaultSourceResolver::class);
});
