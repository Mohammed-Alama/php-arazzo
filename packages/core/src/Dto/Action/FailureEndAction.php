<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;
use Alama\Arazzo\Dto\SuccessCriterion;

final readonly class FailureEndAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(string $name, array $criteria)
    {
        parent::__construct($name, ActionKind::End, $criteria);
    }
}
