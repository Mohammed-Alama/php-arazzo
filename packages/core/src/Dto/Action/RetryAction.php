<?php

declare(strict_types=1);

namespace Alama\Arazzo\Dto\Action;

use Alama\Arazzo\Dto\Enum\ActionKind;
use Alama\Arazzo\Dto\SuccessCriterion;

final readonly class RetryAction extends FailureAction
{
    /** @param list<SuccessCriterion> $criteria */
    public function __construct(
        string $name,
        public ?int $retryAfter,
        public ?int $retryLimit,
        public ?string $stepId,
        public ?string $workflowId,
        array $criteria,
    ) {
        parent::__construct($name, ActionKind::Retry, $criteria);
    }
}
