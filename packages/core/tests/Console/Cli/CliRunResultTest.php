<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Console\Cli;

use Alama\Arazzo\Console\Cli\CliRunResult;
use Alama\Arazzo\Console\Cli\InProcessExecutionRegistry;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExecutionStatus;

it('exposes the raw constructor values', function (): void {
    $result = new CliRunResult('exec_1', 'succeeded', false);

    expect($result->executionId)->toBe('exec_1')
        ->and($result->status)->toBe('succeeded')
        ->and($result->suspended)->toBeFalse();
});

it('reports success and failure from the status string', function (): void {
    expect((new CliRunResult('e', 'succeeded', false))->succeeded())->toBeTrue()
        ->and((new CliRunResult('e', 'succeeded', false))->failed())->toBeFalse()
        ->and((new CliRunResult('e', 'failed', false))->failed())->toBeTrue()
        ->and((new CliRunResult('e', 'failed', false))->succeeded())->toBeFalse();
});

it('reads the final status from an in-process registry when succeeded', function (): void {
    $registry = new InProcessExecutionRegistry();
    $registry->complete('exec_1', ExecutionStatus::Succeeded);

    $result = CliRunResult::fromStatus('exec_1', $registry);

    expect($result->status)->toBe('succeeded')
        ->and($result->suspended)->toBeFalse()
        ->and($result->succeeded())->toBeTrue();
});

it('reads a failed status from an in-process registry', function (): void {
    $registry = new InProcessExecutionRegistry();
    $registry->complete('exec_1', ExecutionStatus::Failed);

    $result = CliRunResult::fromStatus('exec_1', $registry);

    expect($result->status)->toBe('failed')
        ->and($result->suspended)->toBeFalse()
        ->and($result->failed())->toBeTrue();
});

it('treats an in-process run still running as suspended', function (): void {
    $registry = new InProcessExecutionRegistry();
    $registry->start('exec_1', 'def_1', 'wf');

    $result = CliRunResult::fromStatus('exec_1', $registry);

    expect($result->status)->toBe('running')
        ->and($result->suspended)->toBeTrue();
});

it('falls back to running when the in-process registry has no record', function (): void {
    $registry = new InProcessExecutionRegistry();

    $result = CliRunResult::fromStatus('exec_missing', $registry);

    expect($result->status)->toBe('running')
        ->and($result->suspended)->toBeTrue();
});

it('reports running and suspended for a non in-process registry', function (): void {
    $registry = new class() implements ExecutionRegistryInterface
    {
        public function start(string $executionId, string $definitionId, string $workflowId): void {}

        public function complete(string $executionId, ExecutionStatus $status): void {}
    };

    $result = CliRunResult::fromStatus('exec_1', $registry);

    expect($result->status)->toBe('running')
        ->and($result->suspended)->toBeTrue()
        ->and($result->succeeded())->toBeFalse();
});
