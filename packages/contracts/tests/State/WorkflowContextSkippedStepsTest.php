<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Contracts\State;

use Alama\Arazzo\Contracts\Spec\Enum\StepStatus;
use Alama\Arazzo\Contracts\State\WorkflowContext;

describe('WorkflowContext skippedSteps', function () {
    it('defaults to an empty skippedSteps list', function (): void {
        $context = new WorkflowContext('def_1');

        expect($context->getSkippedSteps())->toBe([]);
    });

    it('round-trips skippedSteps through toArray and fromPersisted', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list', 'fetch']);

        $persisted = $context->toArray();
        $restored = WorkflowContext::fromPersisted($persisted, 'exec_1');

        expect($restored->getSkippedSteps())->toBe(['list', 'fetch']);
    });

    it('carries skippedSteps through withSkippedSteps immutably', function (): void {
        $context = new WorkflowContext('def_1')
            ->withSkippedSteps(['list']);

        expect($context->getSkippedSteps())->toBe(['list']);

        $updated = $context->withSkippedSteps(['list', 'fetch']);

        expect($updated->getSkippedSteps())->toBe(['list', 'fetch'])
            ->and($context->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withWorkflowId', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withWorkflowId('wf_2');

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withInputs', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withInputs(['name' => 'value']);

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withStepStatus', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withStepStatus('step_1', StepStatus::Succeeded);

        expect($updated->getSkippedSteps())->toBe(['list']);
    });

    it('reconciled prefers persisted skippedSteps over incoming', function (): void {
        $incoming = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['incoming_step']);

        $persisted = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['persisted_step'])
            ->toArray();

        $restored = WorkflowContext::reconciled($incoming, $persisted, 'exec_1');

        expect($restored->getSkippedSteps())->toBe(['persisted_step']);
    });

    it('toArray includes skippedSteps key', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $array = $context->toArray();

        expect(array_key_exists('skippedSteps', $array))->toBeTrue()
            ->and($array['skippedSteps'])->toBe(['list']);
    });

    it('carries skippedSteps through withStepResult', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withStepResult('step_1', ['statusCode' => 200]);

        expect($updated->getSkippedSteps())->toBe(['list']);
        expect($updated->getSteps()['step_1']['statusCode'])->toBe(200);
    });

    it('carries skippedSteps through withStepRequest', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withStepRequest('step_1', ['method' => 'GET']);

        expect($updated->getSkippedSteps())->toBe(['list']);
        expect($updated->getSteps()['step_1']['request']['method'])->toBe('GET');
    });

    it('carries skippedSteps through withStepResponse', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list']);

        $updated = $context->withStepResponse('step_1', ['status' => 200]);

        expect($updated->getSkippedSteps())->toBe(['list']);
        expect($updated->getSteps()['step_1']['response']['status'])->toBe(200);
    });
});
