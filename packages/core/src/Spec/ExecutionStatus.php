<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec;

enum ExecutionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
