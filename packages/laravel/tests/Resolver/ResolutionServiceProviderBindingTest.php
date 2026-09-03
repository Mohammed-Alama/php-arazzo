<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Resolution;

use Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Document\Resolver\SourceRegistry;

it('resolves SourceResolver from the container as a SourceRegistry', function (): void {
    $resolver = $this->app->make(SourceResolver::class);

    expect($resolver)->toBeInstanceOf(SourceRegistry::class);
});
