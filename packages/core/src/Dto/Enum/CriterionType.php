<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Enum;

enum CriterionType: string
{
    case Simple = 'simple';
    case Regex = 'regex';
    case JsonPath = 'jsonpath';
    case XPath = 'xpath';
}
