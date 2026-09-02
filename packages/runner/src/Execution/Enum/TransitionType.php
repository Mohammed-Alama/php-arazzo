<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Enum;

enum TransitionType: string
{
    case Next = 'next';
    case Retry = 'retry';
    case Goto = 'goto';
    case End = 'end';
    case Suspend = 'suspend';
    case Invoke = 'invoke';
}
