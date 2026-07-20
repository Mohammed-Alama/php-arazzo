<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
