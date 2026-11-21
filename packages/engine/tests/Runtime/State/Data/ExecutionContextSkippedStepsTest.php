<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Engine\Runtime\State\Data;

use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Runtime\State\Data\ExecutionContext;

describe('Engine ExecutionContext skippedSteps', function () {
    it('defaults to an empty skippedSteps list', function (): void {
        $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1');

        expect($context->getSkippedSteps())->toBe([]);
    });

    it('carries skippedSteps through fromWorkflowContext', function (): void {
        $context = (new WorkflowContext('def_1'))
            ->withWorkflowId('wf_1')
            ->withExecutionId('exec_1')
            ->withSkippedSteps(['list', 'fetch']);

        $engineContext = ExecutionContext::fromWorkflowContext($context);

        expect($engineContext->getSkippedSteps())->toBe(['list', 'fetch']);
    });

    it('carries skippedSteps through toWorkflowContext', function (): void {
        $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $workflowContext = $context->toWorkflowContext();

        expect($workflowContext->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through toExecutionState', function (): void {
        $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $state = $context->toExecutionState();

        expect($state->getSkippedSteps())->toBe(['list']);
    });

    it('carries skippedSteps through withSkippedSteps immutably', function (): void {
        $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        expect($context->getSkippedSteps())->toBe(['list']);

        $updated = $context->withSkippedSteps(['list', 'fetch']);

        expect($updated->getSkippedSteps())->toBe(['list', 'fetch'])
            ->and($context->getSkippedSteps())->toBe(['list']);
    });

    it('toArray includes skippedSteps key', function (): void {
        $context = ExecutionContext::start('exec_1', 'def_1', 'wf_1')
            ->withSkippedSteps(['list']);

        $array = $context->toArray();

        expect(array_key_exists('skippedSteps', $array))->toBeTrue()
            ->and($array['skippedSteps'])->toBe(['list']);
    });
});
