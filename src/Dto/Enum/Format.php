<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Enum;

enum Format: string
{
    case Yaml = 'yaml';
    case Json = 'json';

    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'yaml', 'yml' => self::Yaml,
            'json'        => self::Json,
            default       => null,
        };
    }
}
