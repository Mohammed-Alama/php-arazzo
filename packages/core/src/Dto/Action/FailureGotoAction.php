<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;
use Alama\Arazzo\Dto\SuccessCriterion;

final readonly class FailureGotoAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Goto, $criteria);
    }
}
