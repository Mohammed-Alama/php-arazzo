<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Document\DocumentInterface;
use Alama\Arazzo\Expression\ExpressionEngineInterface;
use Alama\Arazzo\Runner\AsyncExecutionGraph;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\Data\RunControlFlow;
use Alama\Arazzo\Runner\Execution\Data\RunPersistence;
use Alama\Arazzo\Runner\Protocol\AsyncApiStepExecutor;
use Alama\Arazzo\Runner\Protocol\HttpStepExecutor;
use Alama\Arazzo\Runner\Protocol\SubWorkflowStepExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Assembles the async/queue execution graph. Every node is constructed
 * here — hosts supply ports and config through {@see AsyncGraphSeams}.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class AsyncExecutionGraphAssembler
{
    public function __construct(
        private readonly DocumentInterface $documents,
        private readonly ExpressionEngineInterface $engine,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    public function assemble(AsyncGraphSeams $seams): AsyncExecutionGraph
    {
        $client = $this->httpClient ?? new Client();
        $factory = $this->requestFactory ?? new HttpFactory();

        $openApiExecutor = $seams->openApiExecutor ?? new DefaultOpenApiExecutor($client, $factory, $seams->logger);
        $expressionResolver = $seams->expressionResolver ?? new ExecutionExpressionResolver(
            $this->engine,
            new StepOutputExtractor($this->documents, $this->engine),
            new ResponseSchemaValidator($this->documents),
        );

        $workflowEngine = new WorkflowEngine(
            $expressionResolver,
            maxRetryAttempts: $seams->retryCeiling,
            retryBackoffMultiplier: $seams->retryBackoffMultiplier,
        );

        $injector = new IdempotencyKeyInjector(
            enabledDefault: $seams->idempotencyEnabled,
            headerDefault: $seams->idempotencyHeader,
        );

        $stepExecutor = new StepExecutor(
            $openApiExecutor,
            $expressionResolver,
            $this->documents,
            engine: $this->engine,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $httpStepExecutor = new HttpStepExecutor(
            $openApiExecutor,
            $expressionResolver,
            $this->documents,
            engine: $this->engine,
            strictValidationDefault: $seams->strictValidation,
            injector: $injector,
        );

        $workflowExecutor = new WorkflowExecutor(
            $stepExecutor,
            workflowEngine: $workflowEngine,
            preflight: $this->documents,
        );

        $subWorkflowExecutor = new SubWorkflowStepExecutor($workflowExecutor, $this->engine);
        $invoker = new SubWorkflowInvoker($seams->definitionRegistry, $workflowExecutor, $this->engine);

        $persistence = new RunPersistence(
            $seams->stateStore,
            $seams->eventLedger,
            $seams->executionRegistry,
        );

        $controlFlow = new RunControlFlow(
            workflowEngine: $workflowEngine,
            queueDriver: $seams->queueDriver,
            preflight: $this->documents,
        );

        $outcomeHandler = new StepOutcomeHandler(
            $persistence,
            $controlFlow,
            $seams->pendingCorrelationRegistry,
            $invoker,
            $this->engine,
            $seams->stateTtlSeconds,
        );

        $asyncRequestFactory = $seams->requestFactory ?? new HttpFactory();
        $asyncExecutor = new AsyncApiStepExecutor(
            $seams->pendingCorrelationRegistry,
            $this->engine,
            $seams->httpClient,
            $asyncRequestFactory,
            new HttpFactory(),
            new HttpFactory(),
        );

        $resumer = new CorrelationResumer(
            $seams->pendingCorrelationRegistry,
            $seams->stateStore,
            $seams->definitionRegistry,
            $expressionResolver,
            $outcomeHandler,
            $seams->eventLedger,
            $seams->lockManager,
        );

        $protocolExecutors = [$subWorkflowExecutor, $httpStepExecutor, $asyncExecutor];

        $worker = new StepExecutionWorker(
            $persistence,
            $seams->lockManager,
            $seams->definitionRegistry,
            $expressionResolver,
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
            expressionResolver: $expressionResolver,
            protocolExecutors: $protocolExecutors,
        );
    }
}
