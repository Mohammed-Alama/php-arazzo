<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;
use Alama\Arazzo\Runtime\State\InMemoryStateStore;
use Alama\Arazzo\Runtime\State\StoredWorkflowStateRepository;

function storedRepositoryContext(): WorkflowContextInterface
{
    return new class() implements WorkflowContextInterface
    {
        public function getInputs(): array
        {
            return ['x' => 1];
        }

        public function getSteps(): array
        {
            return [];
        }

        public function getComponents(): array
        {
            return [];
        }

        public function getWorkflows(): array
        {
            return [];
        }

        public function getStepStatus(string $stepId): ?StepStatus
        {
            return null;
        }

        public function getWorkflowId(): ?string
        {
            return 'wf-1';
        }
    };
}

it('implements WorkflowStateRepositoryInterface', function (): void {
    expect(is_subclass_of(StoredWorkflowStateRepository::class, WorkflowStateRepositoryInterface::class, true))->toBeTrue();
});

it('round-trips through the versioned envelope', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $loaded = $repo->load('exec-1');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['x' => 1])
        ->and($loaded->getWorkflowId())->toBe('wf-1');
});

it('returns null for missing execution', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    expect($repo->load('nonexistent'))->toBeNull();
});

it('delegates delete to StateStoreInterface', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);
    $repo->delete('exec-1');

    expect($repo->load('exec-1'))->toBeNull();
});

it('persists the versioned envelope and exposes the derived StepState', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);
    $context = storedRepositoryContext();

    $repo->save('exec-1', $context);

    $raw = $store->load('exec-1');
    expect(is_int($raw['version'] ?? null))->toBeTrue()
        ->and($raw['stepState'] ?? null)->toBe(StepState::Pending->value)
        ->and($raw['payload'] ?? null)->toBeArray()
        ->and($repo->loadStepState('exec-1'))->toBe(StepState::Pending);
});

it('backward-compat loads raw WorkflowContext::toArray() payloads', function (): void {
    $store = new InMemoryStateStore();
    $repo = new StoredWorkflowStateRepository($store);

    // Simulate a raw payload from the old save path (no envelope)
    $rawPayload = [
        'definitionId' => 'test-def',
        'workflowId' => 'wf-1',
        'steps' => [],
        'inputs' => ['old' => true],
        'components' => [],
    ];
    $store->save('exec-old', $rawPayload);

    $loaded = $repo->load('exec-old');
    expect($loaded)->not->toBeNull()
        ->and($loaded->getInputs())->toBe(['old' => true]);
});
