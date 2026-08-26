<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\WritableDefinitionRegistryInterface;
use Alama\Arazzo\Spec\ArazzoDocument;

class InMemoryDefinitionRegistry implements WritableDefinitionRegistryInterface
{
    /** @var array<string, ArazzoDocument> */
    private array $registry = [];

    public function register(ArazzoDocument $document): string
    {
        $id = 'in_memory_'.spl_object_id($document);
        $this->registry[$id] = $document;

        return $id;
    }

    public function get(string $definitionId): ?ArazzoDocument
    {
        return $this->registry[$definitionId] ?? null;
    }
}
