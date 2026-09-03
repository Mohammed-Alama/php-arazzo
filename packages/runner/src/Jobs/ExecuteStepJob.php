<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Jobs;

use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\WorkflowContext;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {}
}
