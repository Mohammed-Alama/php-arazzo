<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Runner\Execution\HttpStepExecutor;
use Alama\Arazzo\Runner\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Runner\Execution\RunControlFlow;
use Alama\Arazzo\Runner\Execution\RunPersistence;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\SyncQueueDriver;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
use Alama\Arazzo\Runner\Jobs\ExecuteStepJob;
use Alama\Arazzo\Tests\Support\FakeLockManager;
use Alama\Arazzo\Tests\Support\RecordingEventLedger;
use Alama\Arazzo\Tests\Support\RecordingExecutionRegistry;
use Alama\Arazzo\Tests\Support\RecordingStateStore;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Executes a fixture through the QUEUED adapter: every step runs through
 * StepExecutionWorker, and follow-up steps are driven by draining the
 * recorded queue until the run terminates.
 */
final class QueueFixtureRunner extends ConformanceHarness
{
    /**
     * @param array<string, mixed> $fixture
     *
     * @return array<string, mixed>
     */
    public function run(array $fixture): array
    {
        $document = $this->prepare($fixture);

        if (($document->workflows[0] ?? null) === null) {
            throw new \InvalidArgumentException('Fixture has no workflows');
        }

        $workflow = $document->workflows[0];
        $operationResolver = $this->operationResolver($this->sourceRegistry);
        $resolver = $this->resolver($operationResolver);

        $definitionRegistry = new InMemoryDefinitionRegistry();
        $definitionId = $definitionRegistry->register($document);
        $queue = new SyncQueueDriver();

        $worker = new StepExecutionWorker(
            new RunPersistence(
                new RecordingStateStore(),
                new RecordingEventLedger(),
                new RecordingExecutionRegistry(),
            ),
            new FakeLockManager(),
            $definitionRegistry,
            $resolver,
            [new HttpStepExecutor(
                new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                $resolver,
                $operationResolver,
            )],
            new RunControlFlow(new WorkflowEngine($resolver), $queue, events: $this->events),
        );

        $executionId = 'parity_' . bin2hex(random_bytes(4));
        $context = new WorkflowContext(
            definitionId: $definitionId,
            inputs: $fixture['inputs'] ?? [],
            workflowId: $workflow->workflowId,
            executionId: $executionId,
        );
        $queue->dispatch(new ExecuteStepJob($workflow->steps[0], $context));

        try {
            while ($queued = array_shift($queue->dispatched)) {
                /** @var ExecuteStepJob $job */
                $job = $queued['job'];
                $worker->handle($job);
            }
        } catch (\Throwable) {
            // Terminal failures are observed through the event stream.
        }

        return $this->observe();
    }
}
