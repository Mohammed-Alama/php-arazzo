<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Laravel\Support\AsyncGraphResolver;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;
use Illuminate\Contracts\Container\Container;

/**
 * Execution pipeline: one lazily-sampled AsyncExecutionGraph. Graph nodes are
 * reached through the facade (the graph accessors), not re-exported as aliases
 * — consumers of the laravel package never name the runner's Async\ or Sync\
 * step-execution internals (F2.2 arch guard). WorkflowEngine keeps a
 * config-live binding so retry config re-reads on forget+resolve.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExecutionBindings
{
    public static function register(Container $app): void
    {
        // The three protocol value seams (OpenApiExecutorInterface,
        // OutputExtractorInterface, ResponseValidatorInterface) are owned by
        // ProtocolHttpServiceProvider since F1.3 — they only exist when
        // alama/arazzo-protocol-http is installed, so they are not bound here.

        $app->singleton(AsyncExecutionGraph::class, static fn (Container $app): AsyncExecutionGraph => AsyncGraphResolver::resolve($app));

        // Only the composable graph handle is re-exported: WorkflowExecutor is
        // the sync composition root hosts drive directly. Everything else —
        // worker, resumer, outcome handler, step executor — is reached through
        // the graph's public accessors.
        $app->singleton(WorkflowExecutor::class, static fn (Container $app): WorkflowExecutor => $app->make(AsyncExecutionGraph::class)->workflowExecutor());

        // Config-live: retry knobs re-read whenever this node is re-resolved
        // after forgetInstance, matching the historical contract.
        $app->singleton(WorkflowEngine::class, static function (Container $app): WorkflowEngine {
            return new WorkflowEngine(
                $app->make(EvaluationEngineInterface::class),
                maxRetryAttempts: ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
                retryBackoffMultiplier: ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            );
        });
    }
}
