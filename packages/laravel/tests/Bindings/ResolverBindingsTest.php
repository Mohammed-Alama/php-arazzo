<?php

declare(strict_types=1);

use Alama\Arazzo\Document\Normalizer\OpenApiDocumentLoader;
use Alama\Arazzo\Document\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Document\Resolver\Interfaces\SourceResolver;
use Alama\Arazzo\Document\Resolver\SourceRegistry;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
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

it('resolves the engine facades and the preflight gate', function (): void {
    expect(app(ExpressionEngineInterface::class))->toBeInstanceOf(ExpressionEngine::class)
        ->and(app(ExpressionEngineInterface::class))->toBe(app(ExpressionEngineInterface::class))
        ->and(app(EvaluationEngineInterface::class))->toBeInstanceOf(EvaluationEngine::class)
        ->and(app(EvaluationEngineInterface::class))->toBe(app(EvaluationEngineInterface::class))
        ->and(app(PreflightValidator::class))->toBeInstanceOf(PreflightValidator::class);
});

it('registers the resolver bindings on the container', function (): void {
    ResolverBindings::register($this->app);

    expect(app(SourceRegistry::class))->toBeInstanceOf(SourceRegistry::class);
});
