<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Enum;

enum ActionKind: string
{
    case Goto = 'goto';
    case End = 'end';
    case Retry = 'retry';
}
