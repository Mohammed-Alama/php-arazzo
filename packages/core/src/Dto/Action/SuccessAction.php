<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;
use Alama\Arazzo\Dto\SuccessCriterion;

abstract readonly class SuccessAction extends Action
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
