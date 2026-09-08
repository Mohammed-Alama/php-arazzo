<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\State\Interfaces;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
interface DefinitionRegistryInterface
{
    public function get(string $definitionId): ?ArazzoDocument;
}
