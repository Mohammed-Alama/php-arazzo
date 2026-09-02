<?php

declare(strict_types=1);

namespace Alama\Arazzo\State;

use Alama\Arazzo\Execution\Data\WorkflowContext;

final class ExecutionContext
{
    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, array<string, mixed>>  $stepResults
     * @param  array<string, mixed>  $components
     * @param  list<array<string, mixed>>  $errors
     * @param  list<string>  $workflowCallStack
     */
    public function __construct(
        public readonly string $executionId,
        public readonly string $definitionId,
        public readonly string $workflowId,
        public readonly ?string $currentStepId = null,
        public readonly array $inputs = [],
        public readonly array $stepResults = [],
        public readonly array $components = [],
        public readonly array $errors = [],
        public readonly int $stepsSpent = 0,
        public readonly int $maxSteps = 1000,
        public readonly array $workflowCallStack = [],
        public readonly int $maxWorkflowDepth = 32,
        public readonly string $status = 'running',
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function start(
        string $executionId,
        string $definitionId,
        string $workflowId,
        array $inputs = [],
        int $maxSteps = 1000,
        int $maxWorkflowDepth = 32,
    ): self {
        return new self(
            executionId: $executionId,
            definitionId: $definitionId,
            workflowId: $workflowId,
            currentStepId: null,
            inputs: $inputs,
            stepResults: [],
            components: [],
            errors: [],
            stepsSpent: 0,
            maxSteps: $maxSteps,
            workflowCallStack: [$workflowId],
            maxWorkflowDepth: $maxWorkflowDepth,
            status: 'running',
        );
    }

    public static function fromWorkflowContext(WorkflowContext $context): self
    {
        $stepResults = [];
        foreach ($context->getSteps() as $stepId => $record) {
            if (!is_array($record)) {
                continue;
            }
            $normalized = array_filter($record, function ($key) {
                return is_string($key);
            }, ARRAY_FILTER_USE_KEY);
            $stepResults[$stepId] = $normalized;
        }

        return new self(
            executionId: $context->getExecutionId(),
            definitionId: $context->getDefinitionId(),
            workflowId: $context->getWorkflowId() ?? '',
            currentStepId: null,
            inputs: $context->getInputs(),
            stepResults: $stepResults,
            components: $context->getComponents(),
            errors: [],
            stepsSpent: $context->getStepsSpent(),
            maxSteps: 1000,
            workflowCallStack: $context->getWorkflowCallStack() ?: [$context->getWorkflowId() ?? ''],
            maxWorkflowDepth: 32,
            status: 'running',
        );
    }

    public function toWorkflowContext(): WorkflowContext
    {
        return new WorkflowContext(
            definitionId: $this->definitionId,
            inputs: $this->inputs,
            steps: $this->stepResults,
            components: $this->components,
            workflowId: $this->workflowId,
            executionId: $this->executionId,
            stepsSpent: $this->stepsSpent,
            workflowCallStack: $this->workflowCallStack,
        );
    }

    /**
     * Canonical engine representation: folds per-record attempt counters back
     * into the dedicated stepAttempts map, mirroring ExecutionState::fromContext.
     */
    public function toExecutionState(): ExecutionState
    {
        $state = ExecutionState::start(
            $this->executionId,
            $this->definitionId,
            $this->workflowId,
            $this->inputs,
            maxSteps: $this->maxSteps,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            components: $this->components,
            stepsSpent: $this->stepsSpent,
            workflowCallStack: $this->workflowCallStack ?: [$this->workflowId],
        )->withCurrentStep($this->currentStepId ?? null);

        foreach ($this->stepResults as $stepId => $record) {
            $state = $state->withStepResult($stepId, $record);
            $attempts = $record['attempts'] ?? 0;
            while ($state->attemptFor($stepId) < $attempts) {
                $state = $state->withStepAttempt($stepId);
            }
        }

        foreach ($this->errors as $error) {
            $state = $state->withErrorEntry($error);
        }

        return $state->withStatus($this->status);
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getCurrentStepId(): ?string
    {
        return $this->currentStepId;
    }

    /** @return array<string, mixed> */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /** @return array<string, mixed> */
    public function getComponents(): array
    {
        return $this->components;
    }

    /** @return array<string, array<string, mixed>> */
    public function getStepResults(): array
    {
        return $this->stepResults;
    }

    public function getStepAttempts(string $stepId): int
    {
        $attempts = $this->stepResults[$stepId]['attempts'] ?? 0;

        return is_int($attempts) ? $attempts : 0;
    }

    public function getBudget(): Budget
    {
        return new Budget(
            maxSteps: $this->maxSteps,
            stepsSpent: $this->stepsSpent,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            workflowCallStack: $this->workflowCallStack,
        );
    }

    /** @return list<string> */
    public function getWorkflowCallStack(): array
    {
        return $this->workflowCallStack;
    }

    /** @return list<array<string, mixed>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return array_merge(
            ['inputs' => $this->inputs],
            ['steps' => $this->stepResults],
            ['components' => $this->components],
        );
    }

    /**
     * Stores a step record losslessly: raw arrays pass through untouched
     * (preserving response headers, bodies, failure categories), StepResult
     * objects are serialized.
     *
     * @param  StepResult|array<string, mixed>  $result
     */
    public function withStepResult(string $stepId, StepResult|array $result): self
    {
        $results = $this->stepResults;
        $results[$stepId] = $result instanceof StepResult ? $result->toArray() : $result;

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $results,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    /** Previous attempt count (does not include an in-flight attempt). */
    public function attemptFor(string $stepId): int
    {
        $attempts = $this->stepResults[$stepId]['attempts'] ?? 0;

        return is_int($attempts) ? $attempts : 0;
    }

    public function withStepAttempt(string $stepId): self
    {
        $results = $this->stepResults;
        $record = is_array($results[$stepId] ?? null) ? $results[$stepId] : [];
        $current = is_int($record['attempts'] ?? null) ? $record['attempts'] : 0;
        $record['attempts'] = $current + 1;
        $results[$stepId] = $record;

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $results,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function spendStep(): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent + 1,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function withWorkflow(string $workflowId): self
    {
        $stack = $this->workflowCallStack;
        $stack[] = $workflowId;

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $stack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function withCurrentStep(?string $stepId): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $stepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function withStatus(string $status): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $status,
        );
    }

    public function enterWorkflow(string $workflowId): self
    {
        $stack = $this->workflowCallStack;
        $stack[] = $workflowId;

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $stack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function leaveWorkflow(): self
    {
        $stack = $this->workflowCallStack;
        array_pop($stack);

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $stack[count($stack) - 1] ?? $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $stack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    /** @param array<string, mixed> $inputs */
    public function withInputs(array $inputs): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function withError(ErrorEntry $error): self
    {
        $errors = $this->errors;
        $errors[] = $error->toArray();

        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $errors,
            stepsSpent: $this->stepsSpent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $this->workflowCallStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    /** @param list<string> $callStack */
    public function restoreBudget(int $spent, array $callStack): self
    {
        return new self(
            executionId: $this->executionId,
            definitionId: $this->definitionId,
            workflowId: $this->workflowId,
            currentStepId: $this->currentStepId,
            inputs: $this->inputs,
            stepResults: $this->stepResults,
            components: $this->components,
            errors: $this->errors,
            stepsSpent: $spent,
            maxSteps: $this->maxSteps,
            workflowCallStack: $callStack,
            maxWorkflowDepth: $this->maxWorkflowDepth,
            status: $this->status,
        );
    }

    public function isTerminal(): bool
    {
        return $this->status !== 'running';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId,
            'definitionId' => $this->definitionId,
            'workflowId' => $this->workflowId,
            'currentStepId' => $this->currentStepId,
            'inputs' => $this->inputs,
            'stepResults' => $this->stepResults,
            'components' => $this->components,
            'errors' => $this->errors,
            'stepsSpent' => $this->stepsSpent,
            'maxSteps' => $this->maxSteps,
            'workflowCallStack' => $this->workflowCallStack,
            'maxWorkflowDepth' => $this->maxWorkflowDepth,
            'status' => $this->status,
        ];
    }
}
