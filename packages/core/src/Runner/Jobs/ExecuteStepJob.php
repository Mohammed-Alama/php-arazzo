<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Jobs;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Spec\Step;

class ExecuteStepJob
{
    public function __construct(
        public Step $step,
        public WorkflowContext $context,
    ) {}
}
