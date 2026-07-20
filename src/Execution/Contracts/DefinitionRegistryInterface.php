<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Contracts;

use Alama\LaravelArazzo\Dto\ArazzoDocument;

interface DefinitionRegistryInterface
{
    public function get(string $definitionId): ?ArazzoDocument;
}
