<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Conformance;

use Alama\Arazzo\Execution\Data\RunControlFlow;
use Alama\Arazzo\Execution\Data\RunPersistence;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Execution\DefaultOpenApiExecutor;
use Alama\Arazzo\Execution\InMemoryDefinitionRegistry;
use Alama\Arazzo\Execution\StepExecutionWorker;
use Alama\Arazzo\Execution\StepExecutor;
use Alama\Arazzo\Execution\SyncQueueDriver;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Execution\WorkflowExecutor;
use Alama\Arazzo\Expression\ExpressionEvaluator;
use Alama\Arazzo\Jobs\ExecuteStepJob;
use Alama\Arazzo\Parser\Decoders\SymfonyYamlDecoder;
use Alama\Arazzo\Protocol\HttpStepExecutor;
use Alama\Arazzo\Protocol\SubWorkflowStepExecutor;
use Alama\Arazzo\Tests\Support\FakeLockManager;
use Alama\Arazzo\Tests\Support\RecordingEventLedger;
use Alama\Arazzo\Tests\Support\RecordingExecutionRegistry;
use Alama\Arazzo\Tests\Support\RecordingStateStore;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Queued-adapter counterpart of OaiFixtureRunner: documents containing
 * sub-workflow steps (step.workflowId) execute through StepExecutionWorker,
 * whose protocol list includes SubWorkflowStepExecutor.
 */
final class OaiQueueFixtureRunner extends ConformanceHarness
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function run(array $fixture): array
    {
        $path = (string) $fixture['arazzoFile'];
        $decoder = new SymfonyYamlDecoder();
        $decoded = $decoder->decode((string) file_get_contents($path));

        $sources = [];

        foreach (OaiCorpusRunner::localSources($path) as $name => $sourceDocument) {
            $sources[$name] = $sourceDocument->content;
        }

        $inputs = $fixture['inputs'] ?? [];

        if ($inputs === []) {
            $schema = is_array($decoded['workflows'][0]['inputs'] ?? null) ? $decoded['workflows'][0]['inputs'] : [];

            if ($schema !== []) {
                $inputs = ConformanceFabricator::objectFromSchema($schema);
            }
        }

        $document = $this->prepare([
            'name' => (string) ($fixture['name'] ?? basename($path)),
            'arazzo' => $decoded,
            'sources' => $sources,
            'responses' => $fixture['responses'] ?? [],
            'inputs' => $inputs,
        ]);

        if (($document->workflows[0] ?? null) === null) {
            throw new \InvalidArgumentException('Document has no workflows');
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
            [
                new SubWorkflowStepExecutor(
                    new WorkflowExecutor(
                        new StepExecutor(
                            new FakerOpenApiExecutor(
                                new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                                FakerOpenApiExecutor::referencedBodyFields((string) file_get_contents($path)),
                            ),
                            $resolver,
                            $operationResolver,
                        ),
                        new WorkflowEngine($resolver),
                    ),
                    new ExpressionEvaluator(),
                ),
                new HttpStepExecutor(
                    new FakerOpenApiExecutor(
                        new DefaultOpenApiExecutor($this->http, new HttpFactory()),
                        FakerOpenApiExecutor::referencedBodyFields((string) file_get_contents($path)),
                    ),
                    $resolver,
                    $operationResolver,
                ),
            ],
            new RunControlFlow(new WorkflowEngine($resolver), $queue, events: $this->events),
        );

        $executionId = 'oai_'.bin2hex(random_bytes(4));
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
            // Observed through the event stream.
        }

        return $this->observe();
    }
}
