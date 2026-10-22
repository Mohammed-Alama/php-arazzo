<?php

declare(strict_types=1);

namespace Alama\Arazzo\Cli\Console\Cli;

use Alama\Arazzo\Contracts\Interfaces\HttpClientInterface;
use Alama\Arazzo\Contracts\Interfaces\LockManagerInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\PendingCorrelationRegistryInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Evaluation\EvaluationEngineInterface;
use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Async\ExecuteStepJob;
use Alama\Arazzo\Runner\AsyncGraphSeams;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\OperationExecutorRegistry;
use Alama\Arazzo\Runner\RunnerGraphBuilder;
use Alama\Arazzo\Runner\RunnerGraphBuilderInterface;
use Alama\Arazzo\Runtime\State\Interfaces\DefinitionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;
use Alama\Arazzo\Runtime\State\Interfaces\WritableDefinitionRegistryInterface;
use Alama\Arazzo\Runtime\Telemetry\OtelSetup;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Single-process, resumable workflow runner for CLI usage.
 *
 * Deliberately drives the SAME {@see StepExecutionWorker} the queue adapter
 * uses, draining an in-process {@see SyncQueueDriver}: CLI results come from
 * the canonical async code path (parity by construction), state persists to
 * files, and telemetry exports over OTel — no database required.
 *
 * Post-transition side effects (next-step dispatch, terminal completion)
 * live inside the worker itself; only webhook-driven correlation resume
 * needs the richer Laravel handler stack.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class CliRunner
{
    private readonly SyncQueueDriver $queue;

    private readonly LockManagerInterface $locks;

    private readonly RunnerGraphBuilderInterface $builder;

    private readonly HttpClientInterface $httpClient;

    private readonly PendingCorrelationRegistryInterface $pendingCorrelations;

    private readonly OperationExecutorRegistry $executors;

    /**
     * @param  list<OperationExecutorPluginInterface>  $protocolExecutors
     */
    public function __construct(
        private readonly EvaluationEngineInterface $evaluationEngine,
        private readonly StateStoreInterface $stateStore,
        private readonly DefinitionRegistryInterface $definitions,
        private readonly ExecutionRegistryInterface $registry = new InProcessExecutionRegistry(),
        private readonly EventLedgerInterface $eventLedger = new NullEventLedger(),
        ?LockManagerInterface $locks = null,
        private readonly array $protocolExecutors = [],
        private readonly int $maxQueuedSteps = 10_000,
        ?RunnerGraphBuilderInterface $builder = null,
        ?HttpClientInterface $httpClient = null,
        ?PendingCorrelationRegistryInterface $pendingCorrelations = null,
    ) {
        $this->queue = new SyncQueueDriver();
        $this->locks = $locks ?? new class() implements LockManagerInterface
        {
            public function acquire(string $key, int $ttlSeconds, callable $callback): mixed
            {
                return $callback();
            }

            public function tryAcquire(string $key, int $ttlSeconds): bool
            {
                return true;
            }

            public function release(string $key): void {}
        };
        $this->executors = new OperationExecutorRegistry();
        foreach ($protocolExecutors as $plugin) {
            $this->executors->register($plugin);
        }
        $this->builder = $builder ?? new RunnerGraphBuilder(evaluationEngine: $this->evaluationEngine);
        $this->httpClient = $httpClient ?? new class() implements HttpClientInterface
        {
            public function sendRequest(RequestInterface $request, ?float $timeoutSeconds = null): ResponseInterface
            {
                throw new RuntimeException('The CLI worker does not perform outbound HTTP; register an alama/arazzo-protocol-http executor plugin if a step needs one.');
            }
        };
        $this->pendingCorrelations = $pendingCorrelations ?? new InProcessPendingCorrelationRegistry();
    }

    /**
     * Runs a workflow to completion (or suspension) in this process.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function run(ArazzoDocument $document, string $workflowId, array $inputs = [], ?string $executionId = null, ?string $definitionId = null): CliRunResult
    {
        $executionId ??= 'cli_'.bin2hex(random_bytes(8));
        $workflow = $this->findWorkflow($document, $workflowId);

        if ($workflow === null) {
            throw new RuntimeException("Unknown workflow '{$workflowId}'.");
        }

        $this->registry->start($executionId, $workflowId, $workflowId);

        // The worker resolves the document through this id at job time.
        $definitionId = $this->resolveDefinitionId($document, $definitionId);

        $context = (new WorkflowContext($definitionId, $inputs))
            ->withExecutionId($executionId)
            ->withWorkflowId($workflowId);

        $firstStep = $workflow->steps[0] ?? null;

        if ($firstStep !== null) {
            $this->queue->dispatch(new ExecuteStepJob($firstStep, $context));
        }

        return $this->drain($executionId);
    }

    /**
     * Resumes a persisted run from file state: rebuilds the context, queues
     * the next runnable step of its workflow, and drains again.
     */
    public function resume(ArazzoDocument $document, string $executionId): CliRunResult
    {
        $persisted = $this->stateStore->load($executionId);

        if ($persisted === null) {
            throw new RuntimeException("No persisted state for execution '{$executionId}'.");
        }

        $workflowId = is_string($persisted['workflowId'] ?? null) ? $persisted['workflowId'] : '';
        $workflow = $this->findWorkflow($document, $workflowId);

        if ($workflow === null) {
            throw new RuntimeException("Persisted workflow '{$workflowId}' not found in document.");
        }

        $this->registry->start($executionId, is_string($persisted['definitionId'] ?? null) ? $persisted['definitionId'] : $workflowId, $workflowId);

        $context = WorkflowContext::fromPersisted($persisted, $executionId);

        foreach ($workflow->steps as $step) {
            if ($context->getStepStatus($step->stepId) !== StepStatus::Succeeded) {
                $this->queue->dispatch(new ExecuteStepJob($step, $context));

                break;
            }
        }

        return $this->drain($executionId);
    }

    private function resolveDefinitionId(ArazzoDocument $document, ?string $definitionId): string
    {
        if ($definitionId !== null) {
            return $definitionId;
        }

        if ($this->definitions instanceof WritableDefinitionRegistryInterface) {
            return $this->definitions->register($document);
        }

        throw new RuntimeException('The definition registry is read-only; pass an explicit $definitionId to run().');
    }

    private function drain(string $executionId): CliRunResult
    {
        $runSpan = OtelSetup::getTracer()->spanBuilder('arazzo.workflow.run')
            ->setAttribute('execution.id', $executionId)
            ->startSpan();
        $scope = $runSpan->activate();

        try {
            return $this->drainUnderSpan($executionId);
        } finally {
            $scope->detach();
            $runSpan->end();
        }
    }

    private function drainUnderSpan(string $executionId): CliRunResult
    {
        // CliRunner is a worker-only host: the facade builds the canonical
        // async worker from the seams (no protocol value seams required).
        $worker = $this->builder->buildWorker(new AsyncGraphSeams(
            stateStore: $this->stateStore,
            queueDriver: $this->queue,
            eventLedger: $this->eventLedger,
            executionRegistry: $this->registry,
            pendingCorrelationRegistry: $this->pendingCorrelations,
            definitionRegistry: $this->definitions,
            lockManager: $this->locks,
            httpClient: $this->httpClient,
            executorRegistry: $this->executors,
        ));

        $processed = 0;

        while ($this->queue->dispatched !== []) {
            if ($processed++ >= $this->maxQueuedSteps) {
                throw new RuntimeException("CLI run '{$executionId}' exceeded {$this->maxQueuedSteps} queued steps; possible dispatch loop.");
            }

            $slot = array_shift($this->queue->dispatched);
            $job = $slot['job'] ?? null;

            if ($job instanceof ExecuteStepJob) {
                $worker->handle($job);
            }
        }

        return CliRunResult::fromStatus($executionId, $this->registry);
    }

    private function findWorkflow(ArazzoDocument $document, string $workflowId): ?Workflow
    {
        foreach ($document->workflows as $workflow) {
            if ($workflow->workflowId === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }
}
