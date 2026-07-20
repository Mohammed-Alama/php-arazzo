<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

interface StateStoreInterface
{
    public function save(string $executionId, array $state): void;
    
    public function load(string $executionId): ?array;
}
