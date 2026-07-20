<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\InMemoryDefinitionRegistry;
use Alama\LaravelArazzo\Execution\SyncQueueDriver;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class StepExecutionMockLockManager implements LockManagerInterface {
    public int $acquireCount = 0;
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed {
        $this->acquireCount++;
        return $callback();
    }
}
class StepExecutionMockStateStore implements StateStoreInterface {
    public array $saves = [];
    public function save(string $id, array $state): void { $this->saves[$id] = $state; }
    public function load(string $id): array { return []; }
}
class StepExecutionMockExpressionResolver implements ExpressionResolverInterface {
    public function compileRequest(Step $step, WorkflowContext $context): RequestInterface { 
        return new \GuzzleHttp\Psr7\Request('GET', 'http://localhost');
    }
    public function extractOutputs(Step $step, array $responseData): array { return []; }
}
class StepExecutionMockHttpClient implements HttpClientInterface {
    public function sendRequest(RequestInterface $request): ResponseInterface { 
        return new \GuzzleHttp\Psr7\Response(200);
    }
}
class StepExecutionMockQueueDriver implements QueueDriverInterface {
    public function dispatch(object $job, int $delaySeconds = 0): void {}
}

class StepExecutionWorkerTest extends TestCase
{
    public function test_skips_already_completed_step(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry);

        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $context = (new WorkflowContext('def_1'))->withStepResult('A', ['success' => true]);

        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);

        // Lock should be acquired, but skipped execution
        $this->assertEquals(1, $lockManager->acquireCount);
        $this->assertEmpty($store->saves); // Shouldn't save state if skipped
    }

    public function test_executes_step_and_triggers_engine(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();

        $step = new Step('B', null, null, null, null, [], null, [], [], [], [], []);
        $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
        $definitionId = $definitionRegistry->register($workflow);

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry);

        $context = new WorkflowContext($definitionId);

        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);

        $this->assertArrayHasKey($definitionId, $store->saves);
        $savedContext = $store->saves[$definitionId];
        $this->assertArrayHasKey('B', $savedContext['steps']);
    }

    public function test_dispatches_newly_unlocked_downstream_step_after_success(): void
    {
        $lockManager = new StepExecutionMockLockManager();
        $store = new StepExecutionMockStateStore();
        $resolver = new StepExecutionMockExpressionResolver();
        $client = new StepExecutionMockHttpClient();
        $queue = new SyncQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        $definitionRegistry = new InMemoryDefinitionRegistry();

        $stepA = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $stepB = new Step('B', null, null, null, null, [], null, [], [], [], [], ['A']);
        $workflow = new Workflow('wf_1', null, null, null, [], [$stepA, $stepB], [], [], [], []);
        $definitionId = $definitionRegistry->register($workflow);

        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver, $definitionRegistry);

        $context = new WorkflowContext($definitionId);

        $job = new ExecuteStepJob($stepA, $context);
        $worker->handle($job);

        $this->assertCount(1, $queue->dispatched);
        $dispatchedJob = $queue->dispatched[0]['job'];
        $this->assertSame('B', $dispatchedJob->step->stepId);
    }
}
