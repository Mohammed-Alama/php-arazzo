<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Context;

use Alama\Arazzo\Runner\Execution\StepStatus;
use Alama\Arazzo\Spec\Workflow;

final class WorkflowContext
{
    public ?string $parentRunId = null;

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $steps
     * @param array<string, mixed> $components
     * @param array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}> $workflows
     */
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
        private array $workflows = [],
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

    /**
     * @return array<string, mixed>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    public function getExecutionId(): ?string
    {
        return $this->executionId;
    }

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * @return array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}>
     */
    public function getWorkflows(): array
    {
        return $this->workflows;
    }

    /**
     * @param array{inputs?: array<string, mixed>, outputs?: array<string, mixed>} $data
     */
    public function withWorkflowData(string $workflowId, array $data): self
    {
        $newWorkflows = $this->workflows;
        $existing = $newWorkflows[$workflowId] ?? ['inputs' => [], 'outputs' => []];
        $newWorkflows[$workflowId] = [
            'inputs' => $data['inputs'] ?? $existing['inputs'],
            'outputs' => $data['outputs'] ?? $existing['outputs'],
        ];

        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, workflows: $newWorkflows);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function withWorkflowId(string $workflowId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $workflowId, $this->executionId, workflows: $this->workflows);
    }

    public function withExecutionId(string $executionId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $executionId, workflows: $this->workflows);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function withStepRequest(string $stepId, array $request): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['request'] = $request;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function withStepResponse(string $stepId, array $response): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['response'] = $response;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    public function withStepOutput(string $stepId, string $key, mixed $value): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['outputs'][$key] = $value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    public function withInput(string $name, mixed $value): self
    {
        $inputs = $this->inputs;
        $inputs[$name] = $value;

        return new self($this->definitionId, $inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function withStepInputs(string $stepId, array $inputs): self
    {
        $newSteps = $this->steps;
        $existing = $newSteps[$stepId] ?? [];
        $existing = is_array($existing) ? $existing : [];
        $existing['inputs'] = $inputs;
        $newSteps[$stepId] = $existing;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
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

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
    }

    public function getStepAttempts(string $stepId): int
    {
        return $this->steps[$stepId]['attempts'] ?? 0;
    }

    public function withStepAttemptIncremented(string $stepId): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['attempts'] = ($newSteps[$stepId]['attempts'] ?? 0) + 1;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows);
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
