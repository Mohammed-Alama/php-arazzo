<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Interfaces;

use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Step;

interface ProtocolExecutorRegistryInterface
{
    public function register(string $name, StepProtocolExecutorInterface $executor): void;

    public function resolve(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface;

    /** @return list<string> */
    public function getSupportedProtocols(): array;
}
