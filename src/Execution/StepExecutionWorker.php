<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Execution\Jobs\ExecuteStepJob;
use Alama\LaravelArazzo\Execution\Contracts\LockManagerInterface;
use Alama\LaravelArazzo\Execution\Contracts\StateStoreInterface;
use Alama\LaravelArazzo\Execution\Contracts\HttpClientInterface;
use Alama\LaravelArazzo\Execution\Contracts\ExpressionResolverInterface;

class StepExecutionWorker
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private StateStoreInterface $stateStore,
        private Engine $engine,
        private HttpClientInterface $httpClient,
        private ExpressionResolverInterface $expressionResolver
    ) {}

    public function handle(ExecuteStepJob $job): void
    {
        $lockKey = "workflow_lock_{$job->context->getDefinitionId()}";
        
        $this->lockManager->acquire($lockKey, 30, function() use ($job) {
            $context = $job->context;
            $step = $job->step;
            
            // Idempotency check
            if (array_key_exists($step->stepId, $context->getSteps())) {
                return;
            }
            
            // Implementation continues in next task
        });
    }
}
