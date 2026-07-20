<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\Workflow;

interface DefinitionRegistryInterface
{
    public function register(Workflow $workflow): string;

    public function get(string $definitionId): ?Workflow;
}
