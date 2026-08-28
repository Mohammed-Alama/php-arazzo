<?php

declare(strict_types=1);

namespace Alama\Arazzo\Jobs;

use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {}
}
