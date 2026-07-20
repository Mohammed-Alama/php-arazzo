<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface StateStoreInterface
{
    /**
     * @param array<string, mixed> $state
     */
    public function save(string $workflowId, array $state): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $workflowId): ?array;
}
