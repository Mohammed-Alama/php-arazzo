<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolver;

use Alama\Arazzo\Resolver\Exceptions\UnresolvableReferenceException;

interface ResolvedSource
{
    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed;
}
