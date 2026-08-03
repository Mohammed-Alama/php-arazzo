<?php

declare(strict_types=1);

namespace Alama\Arazzo\Resolution;

use Alama\Arazzo\Resolution\Exceptions\UnresolvableReferenceException;

interface ResolvedSource
{
    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed;
}
