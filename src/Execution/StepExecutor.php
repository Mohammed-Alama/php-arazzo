<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Alama\LaravelArazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\Dto\StepResult;

class StepExecutor
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private ExpressionEvaluator $evaluator
    ) {}

    public function execute(Step $step, VariableContext $context): StepResult
    {
        // This is a stub for the core layout.
        // In the next iterations, we map parameters and invoke PSR-18 client
        return new StepResult($step->stepId, true);
    }
}
