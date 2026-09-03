<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Action;

use Alama\Arazzo\Contracts\Spec\Enum\ActionKind;
use Alama\Arazzo\Contracts\Spec\SuccessCriterion;

abstract readonly class FailureAction extends Action
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        ActionKind $kind,
        public array $criteria,
    ) {
        parent::__construct($name, $kind);
    }
}
