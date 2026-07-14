<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo;

use Alama\LaravelArazzo\Dto\Enum\SourceType;
use Alama\LaravelArazzo\Parser\Parser;
use Alama\LaravelArazzo\Resolution\DefaultSourceResolver;
use Alama\LaravelArazzo\Resolution\Fetchers\CachedFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\HttpFetcher;
use Alama\LaravelArazzo\Resolution\Fetchers\LocalFetcher;
use Alama\LaravelArazzo\Resolution\Parsers\ArazzoSourceParser;
use Alama\LaravelArazzo\Resolution\Parsers\OpenApiSourceParser;
use Alama\LaravelArazzo\Resolution\SourceResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelArazzoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-arazzo');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SourceResolver::class, function ($app) {
            return new DefaultSourceResolver(
                fetchers: [
                    'http' => new CachedFetcher(new HttpFetcher(), 3600),
                    'https' => new CachedFetcher(new HttpFetcher(), 3600),
                    'file' => new LocalFetcher(),
                ],
                parsers: [
                    SourceType::Openapi->value => new OpenApiSourceParser(),
                    SourceType::Arazzo->value => new ArazzoSourceParser(new Parser()),
                ],
            );
        });
    }
}
