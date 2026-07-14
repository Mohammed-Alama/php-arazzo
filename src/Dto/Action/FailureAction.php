<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

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
