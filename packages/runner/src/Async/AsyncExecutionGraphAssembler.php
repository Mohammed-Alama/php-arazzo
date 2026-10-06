<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Execution\OperationRuntime;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\Sync\StepExecutor;
use Alama\Arazzo\Runner\Sync\SubWorkflowStepExecutor;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;

/**
 * Assembles the async/queue execution graph. Every node is constructed
 * here — hosts supply ports and config through {@see AsyncGraphSeams}.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class AsyncExecutionGraphAssembler
{
    public function __construct(
        private readonly OperationRuntime $operations,
        private readonly EvaluationEngineInterface $evaluationEngine,
        /** @phpstan-ignore property.onlyWritten */
        private readonly ExpressionEngineInterface $inspector,
    ) {}

    public function assemble(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $openApiExecutor = $seams->openApiExecutor
            ?? throw new ExecutionException('No HTTP/OpenAPI executor configured; register alama/arazzo-protocol-http (Phase F1).');
        $outputExtractor = $seams->outputExtractor
            ?? throw new ExecutionException('No output extractor configured; register alama/arazzo-protocol-http (Phase F1).');
        $schemaValidator = $seams->schemaValidator
            ?? throw new ExecutionException('No response validator configured; register alama/arazzo-protocol-http (Phase F1).');

        $workflowEngine = new WorkflowEngine(
            $this->evaluationEngine,
            maxRetryAttempts: $seams->retryCeiling,
            retryBackoffMultiplier: $seams->retryBackoffMultiplier,
        );

        $injector = new IdempotencyKeyInjector(
            enabledDefault: $seams->idempotencyEnabled,
            headerDefault: $seams->idempotencyHeader,
        );

        $stepExecutor = new StepExecutor(
            $openApiExecutor,
            $this->operations->resolver,
            $this->evaluationEngine,
            $outputExtractor,
            $schemaValidator,
            inspector: $this->inspector,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $workflowExecutor = new WorkflowExecutor(
            $stepExecutor,
            workflowEngine: $workflowEngine,
            preflight: $this->operations->document,
        );

        // Protocol executors are no longer constructed here: the host
        // registers them (F1.3's registrar for HTTP/AsyncAPI) into the
        // OperationExecutorRegistry it injects through the seams. The one
        // graph-scoped plugin this composes is the sub-workflow executor —
        // it wraps THIS graph's WorkflowExecutor, so it is registered at
        // assemble time. Seams builders must therefore hand over a fresh
        // registry (AsyncGraphResolver copies the container's) so repeated
        // assembly never duplicates it.
        $registry = $seams->executorRegistry;
        if ($registry !== null) {
            $registry->register(new SubWorkflowStepExecutor($workflowExecutor, $this->evaluationEngine));
            $protocolExecutors = $registry->all();
        } else {
            // No registry injected: the worker throws the F1.2-shaped
            // ExecutionException naming arazzo-protocol-http when a step
            // actually needs a protocol executor.
            $protocolExecutors = [];
        }

        $invoker = new SubWorkflowInvoker($seams->definitionRegistry, $workflowExecutor, $this->evaluationEngine);

        $persistence = new RunPersistence(
            $seams->stateStore,
            $seams->eventLedger,
            $seams->executionRegistry,
        );

        $controlFlow = new RunControlFlow(
            workflowEngine: $workflowEngine,
            queueDriver: $seams->queueDriver,
            preflight: $this->operations->document,
        );

        $outcomeHandler = new StepOutcomeHandler(
            $persistence,
            $controlFlow,
            $seams->pendingCorrelationRegistry,
            $invoker,
            $this->evaluationEngine,
            $seams->stateTtlSeconds,
        );

        $resumer = new CorrelationResumer(
            $seams->pendingCorrelationRegistry,
            $seams->stateStore,
            $seams->definitionRegistry,
            $outputExtractor,
            $this->evaluationEngine,
            $outcomeHandler,
            $seams->eventLedger,
            $seams->lockManager,
        );

        $worker = new StepExecutionWorker(
            $persistence,
            $seams->lockManager,
            $seams->definitionRegistry,
            $this->evaluationEngine,
            $registry,
            $controlFlow,
            $seams->stateTtlSeconds,
        );

        return new AsyncExecutionGraph(
            stepExecutor: $stepExecutor,
            workflowExecutor: $workflowExecutor,
            outcomeHandler: $outcomeHandler,
            resumer: $resumer,
            worker: $worker,
            protocolExecutors: $protocolExecutors,
        );
    }
}
