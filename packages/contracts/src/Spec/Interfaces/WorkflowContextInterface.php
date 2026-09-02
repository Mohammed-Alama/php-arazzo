<?php

declare(strict_types=1);

namespace Alama\Arazzo\Spec\Interfaces;

use Alama\Arazzo\Spec\Enum\StepStatus;

interface WorkflowContextInterface
{
    /** @return array<string, mixed> */
    public function getInputs(): array;

    /** @return array<string, mixed> */
    public function getSteps(): array;

    /** @return array<string, mixed> */
    public function getComponents(): array;

    /** @return array<string, array{inputs: array<string, mixed>, outputs: array<string, mixed>}> */
    public function getWorkflows(): array;

    public function getStepStatus(string $stepId): ?StepStatus;

    public function getWorkflowId(): ?string;
}
