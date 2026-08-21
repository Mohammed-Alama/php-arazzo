<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Runner\Context\ExecutionState;
use Alama\Arazzo\Runner\Execution\Enum\TransitionType;

final readonly class Transition
{
    private function __construct(
        public TransitionType $type,
        public ExecutionState $state,
        public ?string $stepId = null,
        public ?string $workflowId = null,
        public int $delaySeconds = 0,
        public ?string $status = null,
    ) {
    }

    public static function next(ExecutionState $state, ?string $stepId): self
    {
        return new self(TransitionType::Next, $state, $stepId);
    }

    public static function retry(ExecutionState $state, string $stepId, int $delaySeconds = 0, ?string $workflowId = null): self
    {
        return new self(TransitionType::Retry, $state, $stepId, $workflowId, $delaySeconds);
    }

    public static function goto(ExecutionState $state, ?string $stepId, string $workflowId): self
    {
        return new self(TransitionType::Goto, $state, $stepId, $workflowId);
    }

    public static function end(ExecutionState $state, string $status): self
    {
        return new self(TransitionType::End, $state->withStatus($status), null, null, 0, $status);
    }

    public static function suspend(ExecutionState $state): self
    {
        return new self(TransitionType::Suspend, $state->withStatus('suspended'));
    }

    public function isTerminal(): bool
    {
        return $this->type === TransitionType::End;
    }
}
