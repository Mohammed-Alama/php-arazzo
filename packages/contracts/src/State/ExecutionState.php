<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\State;

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
     * @param  array<string, mixed>  $inputs
     * @param  array<string, int>  $stepAttempts
     * @param  array<string, mixed>  $stepResults
     * @param  array<string, list<string>>  $dependencies
     * @param  array<string, mixed>  $outputs
     * @param  list<mixed>  $errors
     * @param  list<string>  $workflowCallStack
     * @param  array<string, mixed>  $components
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
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $components
     * @param  list<string>|null  $workflowCallStack
     */
    public static function start(string $executionId, string $definitionId, string $workflowId, array $inputs = [], int $maxSteps = 1000, int $maxWorkflowDepth = 32, array $components = [], int $stepsSpent = 0, ?array $workflowCallStack = null): self
    {
        return new self($executionId, $definitionId, $workflowId, null, $inputs, [], [], [], [], [], $stepsSpent, $maxSteps, $workflowCallStack ?? [$workflowId], $maxWorkflowDepth, $components);
    }

    /**
     * Re-seeds budget/call-stack from a persisted context (queue resume).
     *
     * @param  list<string>  $workflowCallStack
     */
    public function restoreBudget(int $stepsSpent, array $workflowCallStack): self
    {
        return $this->copy(stepsSpent: $stepsSpent, workflowCallStack: $workflowCallStack);
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        // Hydration narrows every field instead of blind-casting it: a corrupt
        // or truncated payload falls back to a default rather than producing a
        // TypeError deep inside a resumed run.
        $workflowId = self::stringField($values, 'workflowId');

        /** @var array<string, mixed> $inputs */
        $inputs = self::arrayField($values, 'inputs');

        /** @var array<string, int> $stepAttempts */
        $stepAttempts = self::arrayField($values, 'stepAttempts');

        /** @var array<string, mixed> $stepResults */
        $stepResults = self::arrayField($values, 'stepResults');

        /** @var array<string, list<string>> $dependencies */
        $dependencies = self::arrayField($values, 'dependencies');

        /** @var array<string, mixed> $outputs */
        $outputs = self::arrayField($values, 'outputs');

        /** @var list<mixed> $errors */
        $errors = self::arrayField($values, 'errors');

        /** @var list<string> $workflowCallStack */
        $workflowCallStack = is_array($values['workflowCallStack'] ?? null)
            ? $values['workflowCallStack']
            : [$workflowId];

        /** @var array<string, mixed> $components */
        $components = self::arrayField($values, 'components');

        return new self(
            self::stringField($values, 'executionId'),
            self::stringField($values, 'definitionId'),
            $workflowId,
            self::nullableStringField($values, 'currentStepId'),
            $inputs,
            $stepAttempts,
            $stepResults,
            $dependencies,
            $outputs,
            $errors,
            self::intField($values, 'stepsSpent', 0),
            self::intField($values, 'maxSteps', 1000),
            $workflowCallStack,
            self::intField($values, 'maxWorkflowDepth', 32),
            $components,
            self::stringField($values, 'status', 'running'),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<mixed>
     */
    private static function arrayField(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $values */
    private static function stringField(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /** @param array<string, mixed> $values */
    private static function intField(array $values, string $key, int $default): int
    {
        $value = $values[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $values */
    private static function nullableStringField(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        // Listed explicitly (rather than get_object_vars) so the persisted shape
        // is a typed array<string, mixed> and its key order is contractual.
        return [
            'executionId' => $this->executionId,
            'definitionId' => $this->definitionId,
            'workflowId' => $this->workflowId,
            'currentStepId' => $this->currentStepId,
            'inputs' => $this->inputs,
            'stepAttempts' => $this->stepAttempts,
            'stepResults' => $this->stepResults,
            'dependencies' => $this->dependencies,
            'outputs' => $this->outputs,
            'errors' => $this->errors,
            'stepsSpent' => $this->stepsSpent,
            'maxSteps' => $this->maxSteps,
            'workflowCallStack' => $this->workflowCallStack,
            'maxWorkflowDepth' => $this->maxWorkflowDepth,
            'components' => $this->components,
            'status' => $this->status,
        ];
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

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, int>  $stepAttempts
     * @param  array<string, mixed>  $stepResults
     * @param  array<string, list<string>>  $dependencies
     * @param  array<string, mixed>  $outputs
     * @param  list<mixed>  $errors
     * @param  list<string>  $workflowCallStack
     * @param  array<string, mixed>  $components
     */
    private function copy(
        ?string $executionId = null, ?string $definitionId = null, ?string $workflowId = null,
        ?string $currentStepId = null, ?array $inputs = null, ?array $stepAttempts = null,
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

    /**
     * Canonical state → context mapping. Folds the step-attempt counters
     * into the step records and carries budget/call-stack, so every
     * consumer sees the same shape (single owner of the mapping).
     */
    public function toContext(): WorkflowContext
    {
        /** @var array<string, array<string, mixed>> $steps */
        $steps = $this->stepResults;

        foreach ($this->stepAttempts as $id => $attempt) {
            $record = is_array($steps[$id] ?? null) ? $steps[$id] : [];

            $record['attempts'] = $attempt;
            $steps[$id] = $record;
        }

        return new WorkflowContext(
            $this->definitionId,
            $this->inputs,
            $steps,
            $this->components,
            $this->workflowId,
            $this->executionId,
            stepsSpent: $this->stepsSpent,
            workflowCallStack: $this->workflowCallStack,
        );
    }

    /**
     * Canonical context → state mapping: replays per-record attempt counts
     * back into the attempt map and inherits budget/call-stack when present.
     */
    public static function fromContext(WorkflowContext $context, ?int $maxSteps = null, ?int $maxWorkflowDepth = null): self
    {
        $state = new self(
            (string) $context->getExecutionId(),
            $context->getDefinitionId(),
            (string) $context->getWorkflowId(),
            null,
            $context->getInputs(),
            [],
            [],
            [],
            [],
            [],
            $context->getStepsSpent(),
            $maxSteps ?? 1000,
            $context->getWorkflowCallStack() ?: [(string) $context->getWorkflowId()],
            $maxWorkflowDepth ?? 32,
            $context->getComponents(),
        );

        foreach ($context->getSteps() as $stepId => $result) {
            if (!is_array($result)) {
                continue;
            }

            /** @var array<string, mixed> $result */
            $attempts = is_int($result['attempts'] ?? null) ? $result['attempts'] : 0;

            $state = $state->withStepResult($stepId, $result);

            while ($state->attemptFor((string) $stepId) < $attempts) {
                $state = $state->withStepAttempt((string) $stepId);
            }
        }

        return $state;
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
     * @param  array<string, mixed>  $entry
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
     * @param  array<string, mixed>  $inputs
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
