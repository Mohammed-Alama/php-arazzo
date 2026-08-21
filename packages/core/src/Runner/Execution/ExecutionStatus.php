<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
