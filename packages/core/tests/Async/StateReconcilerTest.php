<?php

declare(strict_types=1);

use Alama\Arazzo\Async\StateReconciler;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Contracts\WorkflowContext;
use Alama\Arazzo\Tests\Support\RecordingStateStore;

it('passes the job context through when nothing is persisted', function (): void {
    $reconciler = new StateReconciler(new RecordingStateStore());
    $jobContext = (new WorkflowContext('def', ['a' => 1]))->withExecutionId('exec_r');

    expect($reconciler->reconcile($jobContext, 'exec_r'))->toBe($jobContext);
});

it('merges persisted steps over the job context, persisted wins', function (): void {
    $store = new RecordingStateStore();
    $store->save('exec_m', [
        'definitionId' => 'def',
        'workflowId' => 'wf',
        'steps' => ['s1' => ['statusCode' => 200, 'status' => 'succeeded']],
        'inputs' => [],
        'components' => [],
        'stepsSpent' => 4,
        'workflowCallStack' => ['wf'],
    ]);

    $jobContext = (new WorkflowContext('def'))
        ->withExecutionId('exec_m')
        ->withStepResult('s1', ['statusCode' => 500]);

    $merged = (new StateReconciler($store))->reconcile($jobContext, 'exec_m');

    expect($merged->getSteps()['s1']['statusCode'])->toBe(200)
        ->and($merged->getStepsSpent())->toBe(4);
});

it('accepts a pre-loaded payload without touching the store', function (): void {
    $store = Mockery::mock(StateStoreInterface::class);
    $store->shouldReceive('load')->never();

    $payload = [
        'definitionId' => 'def',
        'workflowId' => 'wf',
        'steps' => ['done' => ['status' => 'succeeded']],
        'inputs' => [],
        'components' => [],
    ];

    $merged = (new StateReconciler($store))->reconcile(new WorkflowContext('def'), 'exec_x', $payload);

    expect($merged->getStepStatus('done')?->value ?? null)->toBe('succeeded');
});
