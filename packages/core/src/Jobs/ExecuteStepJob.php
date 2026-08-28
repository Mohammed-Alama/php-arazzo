<?php

declare(strict_types=1);

namespace Alama\Arazzo\Jobs;

use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Spec\Step;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {}
}
