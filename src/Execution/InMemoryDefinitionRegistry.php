<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

use Alama\LaravelArazzo\Dto\Workflow;
use Alama\LaravelArazzo\Execution\Contracts\DefinitionRegistryInterface;

class InMemoryDefinitionRegistry implements DefinitionRegistryInterface
{
    private array $registry = [];

    public function register(Workflow $workflow): string
    {
        // Simple hash for versioning based on workflow content.
        // In real life, it might hash the source JSON/YAML, but here we can just use spl_object_hash or a uniqid for the MVP.
        $id = $workflow->workflowId . '_' . uniqid();
        $this->registry[$id] = $workflow;

        return $id;
    }

    public function get(string $definitionId): ?Workflow
    {
        return $this->registry[$definitionId] ?? null;
    }
}
