<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Enum;

enum ActionKind: string
{
    case Goto = 'goto';
    case End = 'end';
    case Retry = 'retry';
    case Invoke = 'invoke';
}
