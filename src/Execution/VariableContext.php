<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Execution;

class VariableContext
{
    public function __construct(
        private array $inputs = [],
        private array $steps = [],
        private array $components = []
    ) {}

    public function setInput(string $key, mixed $value): void
    {
        $this->inputs[$key] = $value;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function setStepOutput(string $stepId, string $key, mixed $value): void
    {
        $this->steps[$stepId]['outputs'][$key] = $value;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function setStepRequest(string $stepId, array $request): void
    {
        $this->steps[$stepId]['request'] = $request;
    }

    public function setStepResponse(string $stepId, array $response): void
    {
        $this->steps[$stepId]['response'] = $response;
    }
    
    public function getComponents(): array
    {
        return $this->components;
    }
}
