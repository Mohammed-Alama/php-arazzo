<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Events;

class StepExecuted
{
    /**
     * @param array<string, mixed> $requestData
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        public string $workflowId,
        public string $stepId,
        public array $requestData,
        public array $responseData,
    ) {
    }
}
