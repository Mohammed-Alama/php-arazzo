<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State;

use Alama\Arazzo\Contracts\StateStoreInterface;

/**
 * Process-local state store for tests and synchronous in-memory runs.
 */
final class InMemoryStateStore implements StateStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $states = [];

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->states[$executionId] = $state;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array
    {
        return $this->states[$executionId] ?? null;
    }

    public function delete(string $executionId): void
    {
        unset($this->states[$executionId]);
    }
}
