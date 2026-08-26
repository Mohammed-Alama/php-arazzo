<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Action;

use Alama\Arazzo\Spec\Enum\ActionKind;
use Alama\Arazzo\Spec\Parameter;
use Alama\Arazzo\Spec\Reusable;
use Alama\Arazzo\Spec\SuccessCriterion;

final readonly class FailureGotoAction extends FailureAction
{
    /**
     * @param  list<SuccessCriterion>  $criteria
     * @param  list<Parameter|Reusable>  $parameters  1.1: passed to the workflowId target
     */
    public function __construct(
        string $name,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
        public array $parameters = [],
    ) {
        parent::__construct($name, ActionKind::Goto, $criteria);
    }
}
