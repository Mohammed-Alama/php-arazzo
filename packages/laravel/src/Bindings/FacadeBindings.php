<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Contracts\Interfaces\OutputExtractorInterface;
use Alama\Arazzo\Contracts\Interfaces\ResponseValidatorInterface;
use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Protocol\Http\Execution\Interfaces\OpenApiExecutorInterface;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\RunnerFacade;
use Alama\Arazzo\Runner\RunnerFacadeInterface;
use Alama\Arazzo\Runner\RunnerGraphBuilder;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Alama\Arazzo\Sources\Normalizer\OpenApiOperationResolver;
use Alama\Arazzo\Sources\SourceGraph;
use Alama\Arazzo\Sources\SourceRuntime;
use Illuminate\Contracts\Container\Container;

/** Entry-point facade bindings: each *Interface resolves to its self-contained concrete facade.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class FacadeBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(ExpressionEngineInterface::class, fn (): ExpressionEngine => new ExpressionEngine());
        $app->singleton(EvaluationEngineInterface::class, fn (): EvaluationEngine => new EvaluationEngine(expression: new ExpressionEngine()));
        // One runtime, so the document and the resolver behind it share a
        // single transport instead of each building a fetcher map.
        $app->singleton(SourceRuntime::class, fn (Container $app): SourceRuntime => SourceGraph::runtime(
            operations: $app->make(OpenApiOperationResolver::class),
        ));
        $app->singleton(DocumentInterface::class, fn (Container $app): DocumentInterface => $app->make(SourceRuntime::class)->document);
        // One resolver, bound first and then handed to the runtime, so the document
        // and the resolver share a single loader (and its document cache).
        $app->singleton(OpenApiOperationResolver::class, fn (): OpenApiOperationResolver => SourceGraph::operations());
        $app->singleton(RunnerFacadeInterface::class, fn (Container $app): RunnerFacade => new RunnerFacade(
            $app->make(DocumentInterface::class),
            $app->make(OpenApiOperationResolver::class),
            $app->make(EvaluationEngineInterface::class),
            openApiExecutor: $app->bound(OpenApiExecutorInterface::class) ? $app->make(OpenApiExecutorInterface::class) : null,
            outputExtractor: $app->bound(OutputExtractorInterface::class) ? $app->make(OutputExtractorInterface::class) : null,
            schemaValidator: $app->bound(ResponseValidatorInterface::class) ? $app->make(ResponseValidatorInterface::class) : null,
        ));
        $app->singleton(RunnerGraphBuilderInterface::class, function (Container $app): RunnerGraphBuilder {
            return new RunnerGraphBuilder(
                new OperationRuntime(
                    $app->make(DocumentInterface::class),
                    $app->make(OpenApiOperationResolver::class),
                ),
                $app->make(EvaluationEngineInterface::class),
                $app->make(ExpressionEngineInterface::class),
                $app->bound(OpenApiExecutorInterface::class)
                    ? $app->make(OpenApiExecutorInterface::class)
                    : null,
                $app->bound(OutputExtractorInterface::class)
                    ? $app->make(OutputExtractorInterface::class)
                    : null,
                $app->bound(ResponseValidatorInterface::class)
                    ? $app->make(ResponseValidatorInterface::class)
                    : null,
            );
        });
    }
}
