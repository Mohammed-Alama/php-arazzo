<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Action;

use Alama\Arazzo\Spec\Enum\ActionKind;
use Alama\Arazzo\Spec\SuccessCriterion;

final readonly class FailureEndAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(string $name, array $criteria)
    {
        parent::__construct($name, ActionKind::End, $criteria);
    }
}
