<?php

declare(strict_types=1);

use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Expression\SelectorEvaluator;
use Alama\Arazzo\Laravel\Bindings\ResolverBindings;

it('aliases SourceRegistry onto the same instance as SourceResolver', function (): void {
    expect(app(SourceRegistry::class))->toBe(app(SourceResolver::class))
        ->and(app(SourceResolver::class))->toBeInstanceOf(SourceRegistry::class);
});

it('resolves the operation-resolution stack as singletons', function (): void {
    foreach ([OpenApiDocumentLoader::class, OpenApiOperationResolver::class] as $abstract) {
        expect(app($abstract))->toBeInstanceOf($abstract)
            ->and(app($abstract))->toBe(app($abstract));
    }
});

it('resolves capability evaluators and the preflight gate', function (): void {
    expect(app(SelectorEvaluator::class))->toBeInstanceOf(SelectorEvaluator::class)
        ->and(app(SelectorEvaluator::class))->toBe(app(SelectorEvaluator::class))
        ->and(app(PreflightValidator::class))->toBeInstanceOf(PreflightValidator::class);
});

it('registers the resolver bindings on the container', function (): void {
    ResolverBindings::register($this->app);

    expect(app(SourceRegistry::class))->toBeInstanceOf(SourceRegistry::class);
});
