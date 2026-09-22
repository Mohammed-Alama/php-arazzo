<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;

/**
 * Stores and loads the serializable state of a workflow run, keyed by
 * execution id, for safe pause/resume (spec D2, E2).
 */
interface WorkflowStateRepositoryInterface
{
    public function save(string $executionId, WorkflowContextInterface $state): void;

    public function load(string $executionId): ?WorkflowContextInterface;

    public function delete(string $executionId): void;
}
