<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Enum;

enum CriterionType: string
{
    case Simple = 'simple';
    case Regex = 'regex';
    case JsonPath = 'jsonpath';
    case XPath = 'xpath';
}
