<?php
declare(strict_types=1);
namespace Alama\LaravelArazzo\Execution;

final class WorkflowContext
{
    public function __construct(
        private string $definitionId,
        private array $inputs = [],
        private array $steps = [],
        private array $components = []
    ) {}

    public function getDefinitionId(): string { return $this->definitionId; }
    public function getInputs(): array { return $this->inputs; }
    public function getSteps(): array { return $this->steps; }
    public function getComponents(): array { return $this->components; }

    public function withStepResult(string $stepId, array $result): self
    {
        $newSteps = $this->steps;
        $newSteps[$stepId] = $result;
        return new self($this->definitionId, $this->inputs, $newSteps, $this->components);
    }
}
