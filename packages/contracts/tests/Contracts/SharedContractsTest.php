<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Contracts\Interfaces\BackoffCalculatorInterface;
use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Contracts\Interfaces\StepProtocolExecutorInterface;

it('declares the shared contracts consumers rely on')
    ->expect(interface_exists(BackoffCalculatorInterface::class))
    ->toBeTrue()
    ->and(interface_exists(AiClientInterface::class))
    ->toBeTrue()
    ->and(interface_exists(QueueDriverInterface::class))
    ->toBeTrue()
    ->and(interface_exists(StepProtocolExecutorInterface::class))
    ->toBeTrue();
