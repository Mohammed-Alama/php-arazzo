<?php

declare(strict_types=1);

namespace Alama\Arazzo\License;

use RuntimeException;

final class LicenseException extends RuntimeException
{
    public static function notLicensed(string $feature): self
    {
        return new self(sprintf('Feature "%s" is not covered by any active Arazzo Pro license. ', $feature));
    }

    public static function expired(string $feature): self
    {
        return new self(sprintf('Arazzo Pro license for feature "%s" has expired.', $feature));
    }
}
