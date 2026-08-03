<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution\Jobs;

use Alama\Arazzo\Dto\Step;
use Alama\LaravelArazzo\Execution\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {
    }
}
