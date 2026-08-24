<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Context;

use JsonSerializable;

/**
 * The complete, transport-safe state of a workflow execution.
 *
 * No value is inferred while serialising: this makes a queued execution resume
 * with exactly the same truthy, falsy, null, and empty values it had before it
 * was persisted.
 */
final readonly class ExecutionState implements JsonSerializable
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<string, int> $stepAttempts
     * @param array<string, mixed> $stepResults
     * @param array<string, list<string>> $dependencies
     * @param array<string, mixed> $outputs
     * @param list<mixed> $errors
     * @param list<string> $workflowCallStack
     * @param array<string, mixed> $components
     */
    public function __construct(
        public string $executionId,
        public string $definitionId,
        public string $workflowId,
        public ?string $currentStepId = null,
        public array $inputs = [],
        public array $stepAttempts = [],
        public array $stepResults = [],
        public array $dependencies = [],
        public array $outputs = [],
        public array $errors = [],
        public int $stepsSpent = 0,
        public int $maxSteps = 1000,
        public array $workflowCallStack = [],
        public int $maxWorkflowDepth = 32,
        public array $components = [],
        public string $status = 'running',
    ) {
    }

    /** @param array<string, mixed> $inputs */
    public static function start(string $executionId, string $definitionId, string $workflowId, array $inputs = [], int $maxSteps = 1000, int $maxWorkflowDepth = 32, array $components = []): self
    {
        return new self($executionId, $definitionId, $workflowId, null, $inputs, [], [], [], [], [], 0, $maxSteps, [$workflowId], $maxWorkflowDepth, $components);
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            (string) $values['executionId'],
            (string) ($values['definitionId'] ?? ''),
            (string) $values['workflowId'],
            array_key_exists('currentStepId', $values) ? $values['currentStepId'] : null,
            $values['inputs'] ?? [], $values['stepAttempts'] ?? [], $values['stepResults'] ?? [],
            $values['dependencies'] ?? [], $values['outputs'] ?? [], $values['errors'] ?? [],
            (int) ($values['stepsSpent'] ?? 0), (int) ($values['maxSteps'] ?? 1000),
            $values['workflowCallStack'] ?? [(string) $values['workflowId']],
            (int) ($values['maxWorkflowDepth'] ?? 32), $values['components'] ?? [],
            (string) ($values['status'] ?? 'running'),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function attemptFor(string $stepId): int
    {
        return $this->stepAttempts[$stepId] ?? 0;
    }

    public function withCurrentStep(?string $stepId): self
    {
        return new self($this->executionId, $this->definitionId, $this->workflowId, $stepId, $this->inputs, $this->stepAttempts, $this->stepResults, $this->dependencies, $this->outputs, $this->errors, $this->stepsSpent, $this->maxSteps, $this->workflowCallStack, $this->maxWorkflowDepth, $this->components, $this->status);
    }

    public function withStatus(string $status): self
    {
        return $this->copy(status: $status);
    }

    private function copy(
        ?string $executionId = null, ?string $definitionId = null, ?string $workflowId = null,
        mixed $currentStepId = null, ?array $inputs = null, ?array $stepAttempts = null,
        ?array $stepResults = null, ?array $dependencies = null, ?array $outputs = null,
        ?array $errors = null, ?int $stepsSpent = null, ?int $maxSteps = null,
        ?array $workflowCallStack = null, ?int $maxWorkflowDepth = null, ?array $components = null,
        ?string $status = null,
    ): self {
        return new self($executionId ?? $this->executionId, $definitionId ?? $this->definitionId, $workflowId ?? $this->workflowId,
            $currentStepId === null ? $this->currentStepId : $currentStepId, $inputs ?? $this->inputs, $stepAttempts ?? $this->stepAttempts,
            $stepResults ?? $this->stepResults, $dependencies ?? $this->dependencies, $outputs ?? $this->outputs, $errors ?? $this->errors,
            $stepsSpent ?? $this->stepsSpent, $maxSteps ?? $this->maxSteps, $workflowCallStack ?? $this->workflowCallStack,
            $maxWorkflowDepth ?? $this->maxWorkflowDepth, $components ?? $this->components, $status ?? $this->status);
    }

    public function withWorkflow(string $workflowId): self
    {
        return $this->copy(workflowId: $workflowId);
    }

    public function withOutput(string $name, mixed $value): self
    {
        $outputs = $this->outputs;
        $outputs[$name] = $value;

        return $this->copy(outputs: $outputs);
    }

    /** @param array<string, mixed> $result */
    public function withStepResult(string $stepId, array $result): self
    {
        $results = $this->stepResults;
        $results[$stepId] = $result;

        return $this->copy(stepResults: $results);
    }

    public function withStepAttempt(string $stepId): self
    {
        $attempts = $this->stepAttempts;
        $attempts[$stepId] = ($attempts[$stepId] ?? 0) + 1;

        return $this->copy(stepAttempts: $attempts);
    }

    public function spendStep(): self
    {
        return $this->copy(stepsSpent: $this->stepsSpent + 1);
    }

    public function withError(mixed $error): self
    {
        $errors = $this->errors;
        $errors[] = $error;

        return $this->copy(errors: $errors);
    }

    public function enterWorkflow(string $workflowId): self
    {
        $stack = $this->workflowCallStack;
        $stack[] = $workflowId;

        return $this->copy(workflowId: $workflowId, workflowCallStack: $stack);
    }

    public function leaveWorkflow(): self
    {
        $stack = $this->workflowCallStack;
        array_pop($stack);

        return $this->copy(workflowCallStack: $stack);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function withErrorEntry(array $entry): self
    {
        $errors = $this->errors;
        $errors[] = $entry;

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepAttempts: $this->stepAttempts,
            stepResults: $this->stepResults,
            dependencies: $this->dependencies,
            outputs: $this->outputs,
            errors: $errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            components: $this->components,
            status: $this->status,
        );
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public function withInputs(array $inputs): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $inputs,
            stepAttempts: $this->stepAttempts,
            stepResults: $this->stepResults,
            dependencies: $this->dependencies,
            outputs: $this->outputs,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            components: $this->components,
            status: $this->status,
        );
    }
}
