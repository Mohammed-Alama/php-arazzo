<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine\Data;

use Alama\Arazzo\Contracts\Spec\Action\FailureAction;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Engine\Enum\StepTransitionType;

/**
 * Step-level state transition result.
 */
final readonly class StepTransition
{
    /**
     * @param  list<FailureAction|Reusable>  $actions
     */
    public function __construct(
        public StepState $from,
        public StepState $to,
        public string $reason,
        public StepTransitionType $type = StepTransitionType::Enter,
        public array $actions = [],
    ) {}

    public static function enter(StepState $from, StepState $to, string $reason): self
    {
        return new self($from, $to, $reason, StepTransitionType::Enter);
    }

    public static function guardFailed(StepState $from, string $reason): self
    {
        return new self($from, $from, $reason, StepTransitionType::GuardFailed);
    }

    /**
     * @param  list<FailureAction|Reusable>  $actions
     */
    public static function cancelled(StepState $from, string $reason, array $actions): self
    {
        return new self($from, StepState::Cancelled, $reason, StepTransitionType::Cancelled, $actions);
    }

    /**
     * @param  list<FailureAction|Reusable>  $actions
     */
    public static function timedOut(StepState $from, string $reason, array $actions): self
    {
        return new self($from, StepState::Failed, $reason, StepTransitionType::Timeout, $actions);
    }
}
