<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Expression\Interfaces\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Execution\CorrelationResumer;
use Alama\Arazzo\Runner\Execution\StepExecutionWorker;
use Alama\Arazzo\Runner\Execution\StepExecutor;
use Alama\Arazzo\Runner\Execution\StepOutcomeHandler;
use Alama\Arazzo\Runner\Execution\WorkflowExecutor;

/**
 * The assembled async execution graph. Accessors return the concrete runtime
 * handles the framework packages type against; the @internal sweep (#63)
 * owns the advertised-surface story, these are wiring handles.
 */
final readonly class AsyncExecutionGraph
{
    /**
     * @param  list<StepProtocolExecutorInterface>  $protocolExecutors
     */
    public function __construct(
        private StepExecutor $stepExecutor,
        private WorkflowExecutor $workflowExecutor,
        private StepOutcomeHandler $outcomeHandler,
        private CorrelationResumer $resumer,
        private StepExecutionWorker $worker,
        private ExpressionResolverInterface $expressionResolver,
        private array $protocolExecutors,
    ) {}

    public function stepExecutor(): StepExecutor
    {
        return $this->stepExecutor;
    }

    public function workflowExecutor(): WorkflowExecutor
    {
        return $this->workflowExecutor;
    }

    public function outcomeHandler(): StepOutcomeHandler
    {
        return $this->outcomeHandler;
    }

    public function resumer(): CorrelationResumer
    {
        return $this->resumer;
    }

    public function worker(): StepExecutionWorker
    {
        return $this->worker;
    }

    public function expressionResolver(): ExpressionResolverInterface
    {
        return $this->expressionResolver;
    }

    /**
     * @return list<StepProtocolExecutorInterface>
     */
    public function protocolExecutors(): array
    {
        return $this->protocolExecutors;
    }
}
