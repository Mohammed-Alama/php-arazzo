<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Resolution;

use Alama\LaravelArazzo\Resolution\Exceptions\UnresolvableReferenceException;

interface ResolvedSource
{
    /**
     * @throws UnresolvableReferenceException
     */
    public function extract(string $jsonPointer): mixed;
}
