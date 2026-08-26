<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Cli;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Contracts\DefinitionRegistryInterface;
use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\LockManagerInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\WritableDefinitionRegistryInterface;
use Alama\Arazzo\Execution\RunControlFlow;
use Alama\Arazzo\Execution\RunPersistence;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\SyncQueueDriver;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Telemetry\OtelSetup;
use OpenTelemetry\API\Trace\SpanInterface;
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
 */
final class CliRunner
{
    private readonly SyncQueueDriver $queue;

    private readonly LockManagerInterface $locks;

    /**
     * @param  list<StepProtocolExecutorInterface>  $protocolExecutors
     */
    public function __construct(
        private readonly ExpressionResolverInterface $expressions,
        private readonly StateStoreInterface $stateStore,
        private readonly DefinitionRegistryInterface $definitions,
        private readonly ExecutionRegistryInterface $registry = new InProcessExecutionRegistry(),
        private readonly EventLedgerInterface $eventLedger = new NullEventLedger(),
        ?LockManagerInterface $locks = null,
        private readonly array $protocolExecutors = [],
        private readonly int $maxQueuedSteps = 10_000,
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
            return $this->drainUnderSpan($executionId, $runSpan);
        } finally {
            $scope->detach();
            $runSpan->end();
        }
    }

    private function drainUnderSpan(string $executionId, SpanInterface $runSpan): CliRunResult
    {
        $worker = new StepExecutionWorker(
            new RunPersistence($this->stateStore, $this->eventLedger, $this->registry),
            $this->locks,
            $this->definitions,
            $this->expressions,
            $this->protocolExecutors,
            new RunControlFlow(new WorkflowEngine($this->expressions), $this->queue),
        );

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
