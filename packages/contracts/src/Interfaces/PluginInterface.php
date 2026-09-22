<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Interfaces;

/**
 * A named, priority-ordered extension unit behind a contracts SPI.
 */
interface PluginInterface
{
    public function name(): string;

    public function priority(): int;
}
