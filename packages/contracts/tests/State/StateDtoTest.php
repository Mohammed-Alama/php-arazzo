<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;

it('declares the shared state DTOs')
    ->expect(class_exists(WorkflowContext::class))
    ->toBeTrue()
    ->and(class_exists(ExecutionState::class))
    ->toBeTrue();
