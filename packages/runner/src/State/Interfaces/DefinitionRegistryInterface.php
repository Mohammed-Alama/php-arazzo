<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;

interface DefinitionRegistryInterface
{
    public function get(string $definitionId): ?ArazzoDocument;
}
