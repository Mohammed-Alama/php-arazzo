<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Jobs;

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Execution\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {
    }
}
