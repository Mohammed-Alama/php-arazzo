<?php
namespace Tests\Unit\Execution;

use Alama\LaravelArazzo\Execution\StepExecutionWorker;
use Alama\LaravelArazzo\Execution\WorkflowContext;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\Engine;
use Alama\LaravelArazzo\Execution\DependencyAnalyzer;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\QueueDriverInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MockLockManager implements LockManagerInterface {
    public int $acquireCount = 0;
    public function acquire(string $key, int $ttlSeconds, callable $callback): mixed {
        $this->acquireCount++;
        return $callback();
    }
}
class MockStateStoreWorker implements StateStoreInterface {
    public array $saves = [];
    public function save(string $id, array $state): void { $this->saves[$id] = $state; }
    public function load(string $id): array { return []; }
}
class MockExpressionResolver implements ExpressionResolverInterface {
    public function compileRequest(Step $step, WorkflowContext $context): RequestInterface { return $this->createMock(RequestInterface::class); }
    public function extractOutputs(Step $step, array $responseData): array { return []; }
}
class MockHttpClient implements HttpClientInterface {
    public function sendRequest(RequestInterface $request): ResponseInterface { return $this->createMock(ResponseInterface::class); }
}
class MockQueueDriver implements QueueDriverInterface {
    public function dispatch(object $job, int $delaySeconds = 0): void {}
}

class StepExecutionWorkerTest extends TestCase
{
    public function test_skips_already_completed_step(): void
    {
        $lockManager = new MockLockManager();
        $store = new MockStateStoreWorker();
        $resolver = new MockExpressionResolver();
        $client = new MockHttpClient();
        $queue = new MockQueueDriver();
        $engine = new Engine(new DependencyAnalyzer(), $queue, $store);
        
        $worker = new StepExecutionWorker($lockManager, $store, $engine, $client, $resolver);
        
        $step = new Step('A', null, null, null, null, [], null, [], [], [], [], []);
        $context = (new WorkflowContext('def_1'))->withStepResult('A', ['success' => true]);
        
        $job = new ExecuteStepJob($step, $context);
        $worker->handle($job);
        
        // Lock should be acquired, but skipped execution
        $this->assertEquals(1, $lockManager->acquireCount);
        $this->assertEmpty($store->saves); // Shouldn't save state if skipped
    }
}
