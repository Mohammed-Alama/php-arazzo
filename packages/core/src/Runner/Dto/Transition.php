<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Dto;

final readonly class Transition
{
    private function __construct(
        public string $type,
        public ExecutionState $state,
        public ?string $stepId = null,
        public ?string $workflowId = null,
        public int $delaySeconds = 0,
        public ?string $status = null,
    ) {
    }

    public static function next(ExecutionState $state, ?string $stepId): self
    {
        return new self('next', $state, $stepId);
    }

    public static function retry(ExecutionState $state, string $stepId, int $delaySeconds = 0, ?string $workflowId = null): self
    {
        return new self('retry', $state, $stepId, $workflowId, $delaySeconds);
    }

    public static function goto(ExecutionState $state, ?string $stepId, string $workflowId): self
    {
        return new self('goto', $state, $stepId, $workflowId);
    }

    public static function end(ExecutionState $state, string $status): self
    {
        return new self('end', $state->withStatus($status), null, null, 0, $status);
    }

    public static function suspend(ExecutionState $state): self
    {
        return new self('suspend', $state->withStatus('suspended'));
    }

    public function isTerminal(): bool
    {
        return $this->type === 'end';
    }
}
