<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Engine\Exceptions\ExecutionException;
use Alama\Arazzo\Engine\Execution\IdempotencyKeyInjector;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionEngineInterface;
use Alama\Arazzo\Protocol\Http\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Protocol\Http\Protocol\HttpStepExecutor;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Protocol\SubWorkflowStepExecutor;

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

        $httpStepExecutor = new HttpStepExecutor(
            $openApiExecutor,
            $this->operations->resolver,
            $this->evaluationEngine,
            $this->inspector,
            $outputExtractor,
            $schemaValidator,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $workflowExecutor = new WorkflowExecutor(
            $stepExecutor,
            workflowEngine: $workflowEngine,
            preflight: $this->operations->document,
        );

        $subWorkflowExecutor = new SubWorkflowStepExecutor($workflowExecutor, $this->evaluationEngine);
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

        $asyncRequestFactory = $seams->requestFactory ?? throw new ExecutionException('No PSR-17 request factory configured; register alama/arazzo-protocol-http (Phase F1).');
        $asyncExecutor = new AsyncApiStepExecutor(
            $seams->pendingCorrelationRegistry,
            $this->evaluationEngine,
            $seams->httpClient,
            $asyncRequestFactory,
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

        $protocolExecutors = [$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor];

        $worker = new StepExecutionWorker(
            $persistence,
            $seams->lockManager,
            $seams->definitionRegistry,
            $this->evaluationEngine,
            $protocolExecutors,
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
