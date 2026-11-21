<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\State;

use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Contracts\State\WorkflowContext;

describe('ExecutionState skippedSteps', function () {
    it('defaults to an empty skippedSteps list', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1');

        expect($state->getSkippedSteps())->toBe([]);
    });

    it('round-trips skippedSteps through toArray and fromArray', function (): void {
        $state = new ExecutionState(
            executionId: 'exec_1',
            definitionId: 'def_1',
            workflowId: 'wf_1',
            skippedSteps: ['list', 'fetch'],
        );

        $restored = ExecutionState::fromArray($state->toArray());

        expect($restored->getSkippedSteps())->toBe(['list', 'fetch']);
    });

    it('carries skippedSteps through withSkippedSteps', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        expect($state->getSkippedSteps())->toBe(['list']);

        $updated = $state->withSkippedSteps(['list', 'fetch']);

        expect($updated->getSkippedSteps())->toBe(['list', 'fetch'])
            ->and($state->getSkippedSteps())->toBe(['list']); // immutable
    });

    it('carries skippedSteps through withCurrentStep', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withCurrentStep('fetch');

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withStatus', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withStatus('failed');

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withWorkflow', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withWorkflow('wf_2');

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('restores skippedSteps from context via fromContext', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list', 'fetch']);

        $state = ExecutionState::fromContext($context);

        expect($state->getSkippedSteps())->toBe(['list', 'fetch']);
    });

    it('carries skippedSteps through withWorkflowData', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list'])
            ->withWorkflowData('wf_1', ['outputs' => ['x' => 1]]);

        expect($state->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withErrorEntry', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withErrorEntry(['type' => 'test_error']);

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withInputs', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withInputs(['name' => 'value']);

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('toArray includes skippedSteps key', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $array = $state->toArray();

        expect(array_key_exists('skippedSteps', $array))->toBeTrue()
            ->and($array['skippedSteps'])->toBe(['list']);
    });

    it('jsonSerialize includes skippedSteps', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $json = json_encode($state);

        expect($json)->toContain('"skippedSteps":["list"]');
    });

    it('carries skippedSteps through withError', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withError('test error');

        expect($updated->getSkippedSteps())->toBe(['list']);
        expect($updated->errors)->toBe(['test error']);
    });

    it('carries skippedSteps through withErrorEntry with stepId', function (): void {
        $state = ExecutionState::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $updated = $state->withErrorEntry(['type' => 'test_error', 'stepId' => 'step_1']);

        expect($updated->getSkippedSteps())->toBe(['list']);
        expect($updated->errors)->toBe([['type' => 'test_error', 'stepId' => 'step_1']]);
    });

    it('fromContext ignores non-array step results', function (): void {
        $context = new WorkflowContext(
            'def_1',
            [],
            ['step_1' => 'not an array'],
            [],
            'wf_1',
            'exec_1',
            skippedSteps: ['list'],
        );

        $state = ExecutionState::fromContext($context);

        expect($state->getSkippedSteps())->toBe(['list']);
        expect($state->stepResults)->toBe([]);
    });
});
