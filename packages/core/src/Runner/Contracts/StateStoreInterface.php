<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Contracts;

interface StateStoreInterface
{
    /**
     * @param array<string, mixed> $state
     */
    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void;

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $executionId): ?array;
}
