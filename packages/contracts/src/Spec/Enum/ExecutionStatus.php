<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
