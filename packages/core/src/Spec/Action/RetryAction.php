<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Action;

use Alama\Arazzo\Spec\Enum\ActionKind;
use Alama\Arazzo\Spec\SuccessCriterion;

final readonly class RetryAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?float $retryAfter,
        public ?int $retryLimit,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Retry, $criteria);
    }
}
