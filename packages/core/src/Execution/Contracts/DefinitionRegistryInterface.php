<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Contracts;

use Alama\Arazzo\Dto\ArazzoDocument;

interface DefinitionRegistryInterface
{
    public function get(string $definitionId): ?ArazzoDocument;
}
