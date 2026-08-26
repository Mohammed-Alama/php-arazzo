<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Runner\Async\SuspensionHandler;
use Alama\Arazzo\Runner\Context\WorkflowContext;
use Alama\Arazzo\Runner\Events\CorrelationPending;
use Alama\Arazzo\Spec\ArazzoDocument;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Expression;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Tests\Support\RecordingEventLedger;
use Alama\Arazzo\Tests\Support\RecordingExecutionRegistry;
use Alama\Arazzo\Tests\Support\RecordingStateStore;

function suspensionResolver(): ExpressionResolverInterface
{
    return new class() implements ExpressionResolverInterface
    {
        public function evaluate(Expression $expression, WorkflowContext $context, ?string $currentStepId = null): mixed
        {
            return 'corr_from_expr';
        }

        public function validateResponseSchema(Step $step, int $statusCode, string $contentType, mixed $decodedBody, ?ArazzoDocument $document = null): void {}

        public function extractOutputs(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): array
        {
            return [];
        }

        public function evaluateSuccessCriteria(Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }

        public function evaluateCriteria(array $criteria, Step $step, WorkflowContext $context, ?ArazzoDocument $document = null): bool
        {
            return true;
        }
    };
}

function asyncStep(string $id): Step
{
    return new Step($id, null, null, null, null, [], null, [], [], [], []);
}

function suspensionWorkflow(): Workflow
{
    return new Workflow('wf', null, null, null, [], [], [], [], [], []);
}

function suspensionFixtures(): array
{
    $store = new RecordingStateStore();
    $registry = new RecordingExecutionRegistry();
    $ledger = new RecordingEventLedger();
    $dispatcher = new SimpleEventDispatcher();
    $handler = new SuspensionHandler($store, $registry, $ledger, $dispatcher, suspensionResolver(), 4321);

    return [$handler, $store, $registry, $ledger, $dispatcher];
}

it('persists the suspended status with the configured ttl and marks the run started', function (): void {
    [$handler, $store, $registry] = suspensionFixtures();

    $step = asyncStep('wait');
    $context = (new WorkflowContext('def'))->withExecutionId('exec_s');

    $handler->handle($step, $context, suspensionWorkflow(), 'exec_s');

    expect($store->saved['exec_s']['steps']['wait']['status'])->toBe(StepStatus::Suspended)
        ->and($registry->started[0]['executionId'])->toBe('exec_s')
        ->and($registry->started[0]['workflowId'])->toBe('wf');
});

it('appends a step.suspended ledger entry', function (): void {
    [$handler, , , $ledger] = suspensionFixtures();

    $handler->handle(asyncStep('wait'), (new WorkflowContext('def'))->withExecutionId('e'), suspensionWorkflow(), 'e');

    expect($ledger->eventTypes())->toContain('step.suspended');
});

it('announces CorrelationPending for receive steps carrying correlation coordinates', function (): void {
    [$handler, , , , $dispatcher] = suspensionFixtures();
    $captured = [];
    $dispatcher->subscribe(CorrelationPending::class, function (CorrelationPending $event) use (&$captured) {
        $captured[] = $event;
    });

    $receiveStep = new Step(
        'wait-for-ride',
        null,
        null,
        null,
        null,
        [],
        null,
        [],
        [],
        [],
        [],
        [],
        'receive',
        'channels/rides',
        new Expression('{$inputs.rideId}'),
    );
    $handler->handle($receiveStep, (new WorkflowContext('def'))->withExecutionId('e2'), suspensionWorkflow(), 'e2');

    expect(count($captured))->toBe(1)
        ->and($captured[0]->correlationId)->toBe('corr_from_expr')
        ->and($captured[0]->channelPath)->toBe('channels/rides');
});

it('does not announce CorrelationPending without full receive coordinates', function (): void {
    [$handler, , , , $dispatcher] = suspensionFixtures();
    $captured = [];
    $dispatcher->subscribe(CorrelationPending::class, fn (CorrelationPending $e) => $captured[] = $e);

    $sendStep = new Step('send-thing', null, null, null, null, [], null, [], [], [], [], [], 'send');
    $handler->handle($sendStep, (new WorkflowContext('def'))->withExecutionId('e3'), suspensionWorkflow(), 'e3');

    // Receive but missing correlationId/channelPath -> also silent.
    $halfReceive = new Step('half', null, null, null, null, [], null, [], [], [], [], [], 'receive', 'channels/x');
    $handler->handle($halfReceive, (new WorkflowContext('def'))->withExecutionId('e4'), suspensionWorkflow(), 'e4');

    expect($captured)->toBe([]);
});
