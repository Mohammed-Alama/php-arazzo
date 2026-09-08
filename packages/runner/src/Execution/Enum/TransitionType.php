<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Enum;

/**
 * @internal stays out of the advertised contract; not part of the public API surface
 */
enum TransitionType: string
{
    case Next = 'next';
    case Retry = 'retry';
    case Goto = 'goto';
    case End = 'end';
    case Suspend = 'suspend';
    case Invoke = 'invoke';
}
