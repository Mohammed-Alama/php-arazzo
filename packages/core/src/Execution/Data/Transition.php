<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Data;

use Alama\Arazzo\Execution\Enum\TransitionType;
use Alama\Arazzo\State\Data\ExecutionContext;

final readonly class Transition
{
    private function __construct(
        public TransitionType $type,
        public ExecutionState|ExecutionContext $state,
        public ?string $stepId = null,
        public ?string $workflowId = null,
        public int $delaySeconds = 0,
        public ?string $status = null,
    ) {}

    public static function next(ExecutionState|ExecutionContext $state, ?string $stepId): self
    {
        return new self(TransitionType::Next, $state, $stepId);
    }

    public static function retry(ExecutionState|ExecutionContext $state, string $stepId, int $delaySeconds = 0, ?string $workflowId = null): self
    {
        return new self(TransitionType::Retry, $state, $stepId, $workflowId, $delaySeconds);
    }

    public static function goto(ExecutionState|ExecutionContext $state, ?string $stepId, string $workflowId): self
    {
        return new self(TransitionType::Goto, $state, $stepId, $workflowId);
    }

    public static function end(ExecutionState|ExecutionContext $state, string $status): self
    {
        return new self(TransitionType::End, $state->withStatus($status), null, null, 0, $status);
    }

    public static function invoke(ExecutionState|ExecutionContext $state, string $workflowId): self
    {
        return new self(TransitionType::Invoke, $state->enterWorkflow($workflowId), null, $workflowId);
    }

    public static function suspend(ExecutionState|ExecutionContext $state): self
    {
        return new self(TransitionType::Suspend, $state->withStatus('suspended'));
    }

    public function isTerminal(): bool
    {
        return $this->type === TransitionType::End;
    }
}
