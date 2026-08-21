<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Enum;

enum TransitionType: string
{
    case Next = 'next';
    case Retry = 'retry';
    case Goto = 'goto';
    case End = 'end';
    case Suspend = 'suspend';
}
