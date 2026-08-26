<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts;

use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Step;

interface ProtocolExecutorRegistryInterface
{
    public function register(string $name, StepProtocolExecutorInterface $executor): void;

    public function resolve(Step $step, ArazzoDocument $document): ?StepProtocolExecutorInterface;

    /** @return list<string> */
    public function getSupportedProtocols(): array;
}
