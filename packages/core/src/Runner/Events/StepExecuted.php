<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events;

/**
 * @deprecated Since core-38. Use \Alama\Arazzo\Events\StepExecuted (PSR-14 event). Will be removed in a future major.
 */
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
