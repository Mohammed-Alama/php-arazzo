<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Jobs;

use Alama\Arazzo\Dto\Step;
use Alama\Arazzo\Runner\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {
    }
}
