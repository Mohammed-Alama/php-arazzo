<?php

declare(strict_types=1);

namespace Alama\Arazzo\Expression\Enum;

enum ExpressionType: string
{
    case JsonPath = 'jsonpath';
    case XPath = 'xpath';
    case JsonPointer = 'jsonpointer';
}
