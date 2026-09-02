<?php

declare(strict_types=1);

namespace Alama\Arazzo\State\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;

interface DefinitionRegistryInterface
{
    public function get(string $definitionId): ?ArazzoDocument;
}
