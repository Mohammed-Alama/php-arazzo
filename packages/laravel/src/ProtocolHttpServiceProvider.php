<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel;

use Illuminate\Support\ServiceProvider;

final class ProtocolHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/arazzo.php', 'arazzo');
    }
}
