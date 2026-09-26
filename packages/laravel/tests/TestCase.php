<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Tests;

use Alama\Arazzo\Laravel\LaravelArazzoServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\Dispatcher as LaravelDispatcher;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelArazzoServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Debug: check if provider is loaded
        $providers = $this->app->getLoadedProviders();
        error_log('Loaded providers: '.implode(', ', array_keys($providers)));

        // Ensure packageRegistered is called for proper binding registration
        $provider = $this->app->getProvider(LaravelArazzoServiceProvider::class);
        error_log('Provider: '.($provider ? 'found' : 'NOT FOUND'));
        if ($provider) {
            error_log('Calling packageRegistered...');
            $provider->packageRegistered();
            error_log('packageRegistered called');
        }

        // Ensure Laravel's default event dispatcher is bound
        if (!$this->app->bound(Dispatcher::class)) {
            $this->app->singleton(Dispatcher::class, function ($app) {
                return new LaravelDispatcher($app);
            });
        }
    }
}
