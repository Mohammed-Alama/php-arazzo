<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
}
