<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Enum;

enum SourceType: string
{
    case Openapi = 'openapi';
    case Arazzo = 'arazzo';
    case Asyncapi = 'asyncapi';
}
