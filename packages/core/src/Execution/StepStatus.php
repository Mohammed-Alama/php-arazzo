<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

enum StepStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Suspended = 'suspended';
}
