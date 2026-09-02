<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Console\Cli;

use Alama\Arazzo\Console\Cli\InProcessExecutionRegistry;
use Alama\Arazzo\Spec\Enum\ExecutionStatus;

it('marks a started run as running', function (): void {
    $registry = new InProcessExecutionRegistry();

    $registry->start('exec_1', 'def_1', 'wf');

    expect($registry->statusOf('exec_1'))->toBe(ExecutionStatus::Running);
});

it('returns null for an unknown execution id', function (): void {
    $registry = new InProcessExecutionRegistry();

    expect($registry->statusOf('nope'))->toBeNull();
});

it('records the terminal status on complete', function (): void {
    $registry = new InProcessExecutionRegistry();

    $registry->start('exec_1', 'def_1', 'wf');
    $registry->complete('exec_1', ExecutionStatus::Succeeded);

    expect($registry->statusOf('exec_1'))->toBe(ExecutionStatus::Succeeded);
});

it('does not overwrite an already-started status on repeated start', function (): void {
    $registry = new InProcessExecutionRegistry();

    $registry->start('exec_1', 'def_1', 'wf');
    $registry->complete('exec_1', ExecutionStatus::Failed);
    $registry->start('exec_1', 'def_1', 'wf');

    expect($registry->statusOf('exec_1'))->toBe(ExecutionStatus::Failed);
});

it('tracks several runs independently', function (): void {
    $registry = new InProcessExecutionRegistry();

    $registry->start('exec_1', 'def_1', 'wf');
    $registry->start('exec_2', 'def_1', 'wf');
    $registry->complete('exec_2', ExecutionStatus::Succeeded);

    expect($registry->statusOf('exec_1'))->toBe(ExecutionStatus::Running)
        ->and($registry->statusOf('exec_2'))->toBe(ExecutionStatus::Succeeded);
});
