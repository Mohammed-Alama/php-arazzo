<?php

declare(strict_types=1);

namespace Alama\Arazzo\Async;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\State\Interfaces\StateStoreInterface;

/**
 * Merges the job-carried context with whatever a previous job persisted for
 * the same execution: persisted steps win (they are newer), and the stored
 * budget/call-stack is authoritative.
 */
final class StateReconciler
{
    public function __construct(
        private readonly StateStoreInterface $stateStore,
    ) {}

    /**
     * @param  array<string, mixed>|null  $persisted  pre-loaded payload; null loads from the store
     */
    public function reconcile(WorkflowContext $jobContext, string $executionId, ?array $persisted = null): WorkflowContext
    {
        $persisted ??= $this->stateStore->load($executionId);

        return $persisted === null
            ? $jobContext
            : WorkflowContext::reconciled($jobContext, $persisted, $executionId);
    }
}
