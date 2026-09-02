<?php

declare(strict_types=1);

namespace Alama\Arazzo\State\Interfaces;

use Alama\Arazzo\Spec\ArazzoDocument;

/**
 * A definition registry that can also ingest documents at runtime.
 * Orchestrators that receive raw documents (CLI, tests) depend on this
 * narrower capability; long-lived workers only ever need the base seam.
 */
interface WritableDefinitionRegistryInterface extends DefinitionRegistryInterface
{
    public function register(ArazzoDocument $document): string;
}
