<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\WorkflowStateRepositoryInterface;
use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\Spec\Interfaces\WorkflowContextInterface;

it('declares the durable state repository port')
    ->expect(interface_exists(WorkflowStateRepositoryInterface::class))
    ->toBeTrue();

it('round-trips a context through a stub repository', function (): void {
    $context = new class() implements WorkflowContextInterface
    {
        public function getInputs(): array
        {
            return [];
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

    $repo = new class() implements WorkflowStateRepositoryInterface
    {
        public function save(string $executionId, WorkflowContextInterface $state): void {}

        public function load(string $executionId): ?WorkflowContextInterface
        {
            return null;
        }

        public function delete(string $executionId): void {}
    };

    $repo->save('exec-1', $context);

    expect($repo->load('exec-1'))->toBeNull();
});
