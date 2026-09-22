<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Interfaces\AiClientInterface;
use Alama\Arazzo\Contracts\Interfaces\BackoffCalculatorInterface;
use Alama\Arazzo\Contracts\Interfaces\OperationExecutorPluginInterface;
use Alama\Arazzo\Contracts\Interfaces\PluginInterface;
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

it('declares the plugin base faces')
    ->expect(interface_exists(PluginInterface::class))
    ->toBeTrue()
    ->and(interface_exists(OperationExecutorPluginInterface::class))
    ->toBeTrue()
    ->and(is_subclass_of(OperationExecutorPluginInterface::class, PluginInterface::class))
    ->toBeTrue();
