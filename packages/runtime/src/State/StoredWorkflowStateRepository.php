<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runtime\State;

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runtime\State\Interfaces\StateStoreInterface;

/**
 * WorkflowStateRepositoryInterface backed by the runner's StateStoreInterface.
 *
 * Stores a versioned envelope:
 *   { "version": 1, "stepState": "pending", "payload": { ...WorkflowContext::toArray()... } }
 *
 * Backward-compatible: the loader detects raw payloads (no "version" key) and
 * hydrates them as-is.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StoredWorkflowStateRepository implements WorkflowStateRepositoryInterface
{
    private const ENVELOPE_VERSION = 1;

    public function __construct(
        private StateStoreInterface $stateStore,
        private int $stateTtlSeconds = 86400,
    ) {}

    public function save(string $executionId, WorkflowContextInterface $state): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $state instanceof WorkflowContext ? $state->toArray() : $this->serializeContext($state);

        $envelope = [
            'version' => self::ENVELOPE_VERSION,
            'stepState' => $this->deriveStepState($payload),
            'payload' => $payload,
        ];

        $this->stateStore->save($executionId, $envelope, $this->stateTtlSeconds);
    }

    public function load(string $executionId): ?WorkflowContextInterface
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        // Backward-compat: raw WorkflowContext::toArray() payloads have no "version" key.
        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return WorkflowContext::fromPersisted($raw, $executionId);
        }

        /** @var array<string, mixed> $payload */
        $payload = $raw['payload'] ?? [];

        return WorkflowContext::fromPersisted($payload, $executionId);
    }

    public function delete(string $executionId): void
    {
        $this->stateStore->delete($executionId);
    }

    public function loadStepState(string $executionId): ?StepState
    {
        $raw = $this->stateStore->load($executionId);

        if ($raw === null) {
            return null;
        }

        if (!isset($raw['version']) || !is_int($raw['version'])) {
            return StepState::Pending;
        }

        /** @var string $stepState */
        $stepState = $raw['stepState'] ?? 'pending';

        return StepState::tryFrom($stepState);
    }

    /**
     * @param  array<string, mixed>  $contextArray
     */
    private function deriveStepState(array $contextArray): string
    {
        return StepState::Pending->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContext(WorkflowContextInterface $state): array
    {
        return [
            'definitionId' => $state->getWorkflowId() ?? '',
            'workflowId' => $state->getWorkflowId(),
            'steps' => $state->getSteps(),
            'inputs' => $state->getInputs(),
            'components' => $state->getComponents(),
        ];
    }
}
