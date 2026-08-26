<?php

declare(strict_types=1);

namespace Alama\Arazzo\Protocol;

use Alama\Arazzo\Contracts\ProtocolExecutorRegistryInterface;
use Alama\Arazzo\Contracts\StepProtocolExecutorInterface;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;

/**
 * Chain-of-responsibility over registered protocol executors; the first
 * executor whose supports() returns true wins. Registration order is
 * significant: more specific executors must register before generic ones.
 */
final class ProtocolExecutorRegistry implements ProtocolExecutorRegistryInterface
{
    /** @var array<string, StepProtocolExecutorInterface> */
    private array $executors = [];

    public function register(string $name, StepProtocolExecutorInterface $executor): void
    {
        $this->executors[$name] = $executor;
    }

    public function resolve(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface
    {
        foreach ($this->executors as $executor) {
            if ($executor->supports($step, $document)) {
                return $executor;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function getSupportedProtocols(): array
    {
        return array_keys($this->executors);
    }
}
