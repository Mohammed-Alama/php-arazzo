<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Action;

use Alama\Arazzo\Contracts\Spec\Enum\ActionKind;
use Alama\Arazzo\Contracts\Spec\Parameter;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

final readonly class SuccessGotoAction extends SuccessAction
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
