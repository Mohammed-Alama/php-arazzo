<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Tests;

use Alama\LaravelArazzo\LaravelArazzoServiceProvider;
use Illuminate\Container\Container;
use Spatie\LaravelPackageTools\Package;

it('configurePackage sets package name', function (): void {
    $sp = new LaravelArazzoServiceProvider(new Container());
    $pkg = new Package();
    $sp->configurePackage($pkg);
    expect($pkg->name)->toBe('laravel-arazzo');
});
