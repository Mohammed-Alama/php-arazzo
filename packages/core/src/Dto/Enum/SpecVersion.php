<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Enum;

use InvalidArgumentException;

enum SpecVersion: string
{
    case V1_0 = '1.0.0';
    case V1_1 = '1.1.0';

    public static function fromRaw(string $raw): self
    {
        if (preg_match('/^1\.0\.\d+$/', $raw) === 1) {
            return self::V1_0;
        }

        if (preg_match('/^1\.1\.\d+$/', $raw) === 1) {
            return self::V1_1;
        }

        throw new InvalidArgumentException(
            "Unsupported arazzo version '{$raw}'; expected 1.0.x or 1.1.x.",
        );
    }
}
