<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution\Events;

class StepExecuted
{
    public function __construct(
        public string $workflowId,
        public string $stepId,
        public array $requestData,
        public array $responseData
    ) {}
}
