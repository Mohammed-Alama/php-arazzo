<?php

declare(strict_types=1);

namespace Alama\Arazzo\State;

use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Workflow;

final class WorkflowContext
{
    public ?string $parentRunId = null;

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $steps
     * @param  array<string, mixed>  $components
     * @param  array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}>  $workflows
     */
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
        private ?string $workflowId = null,
        private ?string $executionId = null,
        private array $workflows = [],
        private int $stepsSpent = 0,
        /** @var list<string> */
        private array $workflowCallStack = [],
    ) {
        if ($this->executionId === null) {
            $this->executionId = uniqid('run_', true);
        }
    }

    public function getStepsSpent(): int
    {
        return $this->stepsSpent;
    }

    /** @return list<string> */
    public function getWorkflowCallStack(): array
    {
        return $this->workflowCallStack;
    }

    /**
     * Persistence payload: the canonical serialized shape of a run's
     * state, including budget/call-stack so queue resumes stay exact.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'definitionId' => $this->definitionId,
            'workflowId' => $this->workflowId,
            'steps' => $this->steps,
            'inputs' => $this->inputs,
            'components' => $this->components,
            'stepsSpent' => $this->stepsSpent,
            'workflowCallStack' => $this->workflowCallStack,
        ];
    }

    /**
     * Hydrates a context from a persisted payload (CorrelationResumer).
     *
     * @param  array<string, mixed>  $persisted
     */
    public static function fromPersisted(array $persisted, string $executionId): self
    {
        $definitionId = is_string($persisted['definitionId'] ?? null) ? $persisted['definitionId'] : '';
        $workflowId = is_string($persisted['workflowId'] ?? null) ? $persisted['workflowId'] : '';

        /** @var array<string, mixed> $inputs */
        $inputs = is_array($persisted['inputs'] ?? null) ? $persisted['inputs'] : [];

        /** @var array<string, array<string, mixed>> $steps */
        $steps = is_array($persisted['steps'] ?? null) ? $persisted['steps'] : [];

        /** @var array<string, mixed> $components */
        $components = is_array($persisted['components'] ?? null) ? $persisted['components'] : [];

        $context = new self($definitionId, $inputs, $steps, $components, $workflowId, $executionId);

        if (array_key_exists('stepsSpent', $persisted) || array_key_exists('workflowCallStack', $persisted)) {
            $stack = is_array($persisted['workflowCallStack'] ?? null) ? $persisted['workflowCallStack'] : [];

            $context = $context->withBudget(
                is_int($persisted['stepsSpent'] ?? null) ? $persisted['stepsSpent'] : 0,
                array_values(array_filter($stack, 'is_string')),
            );
        }

        return $context;
    }

    /**
     * Reconciles an incoming job context with the persisted payload:
     * persisted steps win on conflict (they are newer), and the stored
     * budget/call-stack is authoritative.
     *
     * @param  array<string, mixed>  $persisted
     */
    public static function reconciled(self $incoming, array $persisted, string $executionId): self
    {
        /** @var array<string, array<string, mixed>> $persistedSteps */
        $persistedSteps = is_array($persisted['steps'] ?? null) ? $persisted['steps'] : [];

        /** @var array<string, array<string, mixed>> $mergedSteps */
        $mergedSteps = array_merge($incoming->getSteps(), $persistedSteps);

        $restored = new self(
            $incoming->getDefinitionId(),
            $incoming->getInputs(),
            $mergedSteps,
            $incoming->getComponents(),
            $incoming->getWorkflowId(),
            $executionId,
        );

        if (array_key_exists('stepsSpent', $persisted) || array_key_exists('workflowCallStack', $persisted)) {
            $stack = is_array($persisted['workflowCallStack'] ?? null) ? $persisted['workflowCallStack'] : [];

            $restored = $restored->withBudget(
                is_int($persisted['stepsSpent'] ?? null) ? $persisted['stepsSpent'] : 0,
                array_values(array_filter($stack, 'is_string')),
            );
        }

        return $restored;
    }

    /**
     * @param  list<string>  $workflowCallStack
     */
    public function withBudget(int $stepsSpent, array $workflowCallStack): self
    {
        $new = new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
        $new->stepsSpent = $stepsSpent;
        $new->workflowCallStack = $workflowCallStack;

        return $new;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function forChildInvocation(
        WorkflowContext $parent,
        Workflow $target,
        array $inputs,
    ): self {
        // Children SHARE the parent's step budget and call stack: nested
        // attempts consume from the same pool and depth guards still apply.
        $callStack = $parent->getWorkflowCallStack();
        $callStack[] = $target->workflowId;

        $child = new self(
            definitionId: $parent->getDefinitionId(),
            inputs: $inputs,
            steps: [],
            components: $parent->getComponents(),
            workflowId: $target->workflowId,
            executionId: uniqid('run_', true),
            stepsSpent: $parent->getStepsSpent(),
            workflowCallStack: $callStack,
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
     * @param  array{inputs?: array<string, mixed>, outputs?: array<string, mixed>}  $data
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
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    public function withExecutionId(string $executionId): self
    {
        return new self($this->definitionId, $this->inputs, $this->steps, $this->components, $this->workflowId, $executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public function withStepRequest(string $stepId, array $request): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['request'] = $request;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function withStepResponse(string $stepId, array $response): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['response'] = $response;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    public function withStepOutput(string $stepId, string $key, mixed $value): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['outputs'][$key] = $value;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    public function withInput(string $name, mixed $value): self
    {
        $inputs = $this->inputs;
        $inputs[$name] = $value;

        return new self($this->definitionId, $inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function withInputs(array $inputs): self
    {
        return new self($this->definitionId, $inputs, $this->steps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function withStepInputs(string $stepId, array $inputs): self
    {
        $newSteps = $this->steps;
        $existing = $newSteps[$stepId] ?? [];
        $existing = is_array($existing) ? $existing : [];
        $existing['inputs'] = $inputs;
        $newSteps[$stepId] = $existing;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
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

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
    }

    public function getStepAttempts(string $stepId): int
    {
        return $this->steps[$stepId]['attempts'] ?? 0;
    }

    public function withStepAttemptIncremented(string $stepId): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId]['attempts'] = ($newSteps[$stepId]['attempts'] ?? 0) + 1;

        return new self($this->definitionId, $this->inputs, $newSteps, $this->components, $this->workflowId, $this->executionId, workflows: $this->workflows, stepsSpent: $this->stepsSpent, workflowCallStack: $this->workflowCallStack);
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
