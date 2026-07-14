<?php
declare(strict_types=1);

namespace Alama\LaravelArazzo\Dto\Action;

use Alama\LaravelArazzo\Dto\Enum\ActionKind;
use Alama\LaravelArazzo\Dto\SuccessCriterion;

final readonly class FailureEndAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(string $name, array $criteria)
    {
        parent::__construct($name, ActionKind::End, $criteria);
    }
}
