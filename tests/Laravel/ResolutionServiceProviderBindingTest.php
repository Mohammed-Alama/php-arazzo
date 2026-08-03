<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Resolution\DefaultSourceResolver;
use Alama\Arazzo\Resolution\SourceResolver;

it('resolves SourceResolver from the container as a DefaultSourceResolver', function (): void {
    $resolver = $this->app->make(SourceResolver::class);

    expect($resolver)->toBeInstanceOf(DefaultSourceResolver::class);
});
