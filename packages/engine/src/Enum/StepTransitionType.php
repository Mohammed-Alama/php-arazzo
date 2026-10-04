<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine\Enum;

/**
 * Step-level state transition outcome classification.
 */
enum StepTransitionType: string
{
    case Enter = 'enter';
    case GuardFailed = 'guard_failed';
    case Timeout = 'timeout';
    case Cancelled = 'cancelled';
}
