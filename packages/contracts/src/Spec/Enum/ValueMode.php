<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum ValueMode: string
{
    case Literal = 'literal';
    case Selector = 'selector';
}
