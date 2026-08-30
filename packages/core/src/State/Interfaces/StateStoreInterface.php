<?php

declare(strict_types=1);

namespace Alama\Arazzo\State\Interfaces;

// Framework port (kept as a seam): hot state may live in Redis, DB, or memory depending on the adapter.

interface StateStoreInterface
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array;
}
