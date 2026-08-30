<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Support;

use Alama\Arazzo\State\Interfaces\StateStoreInterface;

final class RecordingStateStore implements StateStoreInterface
{
    /** @var array<string, array> */
    public array $saved = [];

    /** @var array<string, array> */
    public array $preloaded = [];

    public function preload(string $executionId, array $state): void
    {
        $this->preloaded[$executionId] = $state;
    }

    public function save(string $executionId, array $state, ?int $ttlSeconds = null): void
    {
        $this->saved[$executionId] = $state;
    }

    public function load(string $executionId): ?array
    {
        return $this->preloaded[$executionId] ?? $this->saved[$executionId] ?? null;
    }
}
