<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\RunnerGraphBuilder;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Alama\Arazzo\Runtime\RunnerFacade;
use Alama\Arazzo\Runtime\RunnerFacadeInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Sources\SourceRuntime;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;

/** Entry-point facade bindings: each *Interface resolves to its self-contained concrete facade.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class FacadeBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(ExpressionEngineInterface::class, fn (): ExpressionEngine => new ExpressionEngine());
        $app->singleton(EvaluationEngineInterface::class, fn (): EvaluationEngine => new EvaluationEngine());
        // One runtime, so the document and the resolver behind it share a
        // single transport instead of each building a fetcher map.
        $app->singleton(SourceRuntime::class, fn (): SourceRuntime => SourceGraph::runtime());
        $app->singleton(DocumentInterface::class, fn (Container $app): DocumentInterface => $app->make(SourceRuntime::class)->document);
        $app->singleton(OpenApiOperationResolver::class, fn (Container $app): OpenApiOperationResolver => $app->make(SourceRuntime::class)->operations);
        $app->singleton(RunnerFacadeInterface::class, fn (Container $app): RunnerFacade => new RunnerFacade(
            $app->make(DocumentInterface::class),
            $app->make(OpenApiOperationResolver::class),
            $app->make(EvaluationEngineInterface::class),
        ));
        $app->singleton(RunnerGraphBuilderInterface::class, function (Container $app): RunnerGraphBuilder {
            return new RunnerGraphBuilder(
                new OperationRuntime(
                    $app->make(DocumentInterface::class),
                    $app->make(OpenApiOperationResolver::class),
                ),
                $app->make(EvaluationEngineInterface::class),
                $app->make(ExpressionEngineInterface::class),
                $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
            );
        });
    }
}
