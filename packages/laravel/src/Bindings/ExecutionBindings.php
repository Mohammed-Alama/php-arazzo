<?php

declare(strict_types=1);

namespace Alama\Arazzo\Laravel\Bindings;

use Alama\Arazzo\Laravel\Support\AsyncGraphResolver;
use Alama\Arazzo\Laravel\Support\ConfigValue;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;
use Illuminate\Contracts\Container\Container;

/**
 * Execution pipeline: one lazily-sampled AsyncExecutionGraph, with the
 * individual nodes re-exported as singleton aliases. WorkflowEngine keeps
 * a config-live binding so retry config re-reads on forget+resolve.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class ExecutionBindings
{
    public static function register(Container $app): void
    {
        $app->singleton(AsyncExecutionGraph::class, static fn (Container $app): AsyncExecutionGraph => AsyncGraphResolver::resolve($app));

        $app->singleton(StepExecutor::class, static fn (Container $app): StepExecutor => $app->make(AsyncExecutionGraph::class)->stepExecutor());
        $app->singleton(WorkflowExecutor::class, static fn (Container $app): WorkflowExecutor => $app->make(AsyncExecutionGraph::class)->workflowExecutor());
        $app->singleton(StepOutcomeHandler::class, static fn (Container $app): StepOutcomeHandler => $app->make(AsyncExecutionGraph::class)->outcomeHandler());
        $app->singleton(CorrelationResumer::class, static fn (Container $app): CorrelationResumer => $app->make(AsyncExecutionGraph::class)->resumer());
        $app->singleton(StepExecutionWorker::class, static fn (Container $app): StepExecutionWorker => $app->make(AsyncExecutionGraph::class)->worker());

        foreach ([
            'Alama\Arazzo\Runner\Protocol\SubWorkflowStepExecutor' => 0,
            'Alama\Arazzo\Runner\Protocol\HttpStepExecutor' => 1,
            'Alama\Arazzo\Runner\Protocol\AsyncApiStepExecutor' => 2,
        ] as $abstract => $index) {
            $app->singleton($abstract, static fn (Container $app): object => $app->make(AsyncExecutionGraph::class)->protocolExecutors()[$index]);
        }

        // Config-live: retry knobs re-read whenever this node is re-resolved
        // after forgetInstance, matching the historical contract.
        $app->singleton(WorkflowEngine::class, static function (Container $app): WorkflowEngine {
            return new WorkflowEngine(
                $app->make(AsyncExecutionGraph::class)->expressionResolver(),
                maxRetryAttempts: ConfigValue::int(config('arazzo.retry_ceiling', 10), 10),
                retryBackoffMultiplier: ConfigValue::float(config('arazzo.retry_backoff_multiplier', 1.0), 1.0),
            );
        });
    }
}
