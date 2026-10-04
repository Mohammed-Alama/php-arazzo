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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Ensure packageRegistered is called for proper binding registration
        $provider = $this->app->getProvider(LaravelArazzoServiceProvider::class);
        if ($provider) {
            $provider->packageRegistered();
        }

        // Ensure Laravel's default event dispatcher is bound
        if (!$this->app->bound(Dispatcher::class)) {
            $this->app->singleton(Dispatcher::class, function ($app) {
                return new LaravelDispatcher($app);
            });
        }
    }
}
