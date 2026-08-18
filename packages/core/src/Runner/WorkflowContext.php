<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner;

use Alama\Arazzo\Dto\Workflow;

final class WorkflowContext
{
    public ?string $parentRunId = null;

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $steps
     * @param array<string, mixed> $components
     */
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
    ) {
        if ($this->executionId === null) {
            $this->executionId = uniqid('run_', true);
        }
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public static function forChildInvocation(
        WorkflowContext $parent,
        Workflow $target,
        array $inputs,
    ): self {
        $child = new self(
            definitionId: $parent->getDefinitionId(),
            inputs: $inputs,
            steps: [],
            components: $parent->getComponents(),
            workflowId: $target->workflowId,
            executionId: uniqid('run_', true),
        );
        $child->parentRunId = $parent->getExecutionId();

        return $child;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    public function getExecutionId(): ?string
    {
        return $this->executionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<string, mixed>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    public function withWorkflowId(string $workflowId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $workflowId, $this->executionId);
    }

    public function withExecutionId(string $executionId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $executionId);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function withStepRequest(string $stepId, array $request): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['request'] = $request;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function withStepResponse(string $stepId, array $response): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['response'] = $response;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function withStepOutput(string $stepId, string $key, mixed $value): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['outputs'][$key] = $value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function getStepStatus(string $stepId): ?StepStatus
    {
        $status = $this->steps[$stepId]['status'] ?? null;

        if ($status instanceof StepStatus) {
            return $status;
        }

        return is_string($status) ? StepStatus::tryFrom($status) : null;
    }

    public function withStepStatus(string $stepId, StepStatus $status): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['status'] = $status;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    public function getStepAttempts(string $stepId): int
    {
        return $this->steps[$stepId]['attempts'] ?? 0;
    }

    public function withStepAttemptIncremented(string $stepId): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['attempts'] = ($newSteps[$stepId]['attempts'] ?? 0) + 1;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rootScope(): array
    {
        return [
            'inputs' => $this->inputs,
            'steps' => $this->steps,
            'components' => $this->components,
        ];
    }
}
