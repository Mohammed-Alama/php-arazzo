<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution;

class VariableContext
{
    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $steps
     * @param array<string, mixed> $components
     */
    public function __construct(
        private array $inputs = [],
        private array $steps = [],
        private array $components = [],
    ) {
    }

    public function setInput(string $key, mixed $value): void
    {
        $this->inputs[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function setStepOutput(string $stepId, string $key, mixed $value): void
    {
        $this->steps[$stepId]['outputs'][$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function setStepRequest(string $stepId, array $request): void
    {
        $this->steps[$stepId]['request'] = $request;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function setStepResponse(string $stepId, array $response): void
    {
        $this->steps[$stepId]['response'] = $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
