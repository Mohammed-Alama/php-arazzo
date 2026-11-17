<?php

declare(strict_types=1);

namespace Alama\Arazzo\Tests\Sync;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Expression;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFactory;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\WorkflowContext;
use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Evaluation\EvaluationEngine;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Expression\ExpressionEngine;
use Alama\Arazzo\Runner\Sync\StepExecutor;
use Alama\Arazzo\Runner\Sync\WorkflowEngine;
use Alama\Arazzo\Runner\Sync\WorkflowExecutor;

function dependencyFixtureWorkflow(string $id, array $dependsOn, array $steps, array $outputs = []): Workflow
{
    return new Workflow($id, null, null, null, $dependsOn, $steps, [], [], $outputs, []);
}

function dependencyFixtureStep(string $id): Step
{
    return StepFactory::http($id, null, new StepFlow(), new StepIo(), 'op');
}

function dependencyFixtureDocument(array $workflows): ArazzoDocument
{
    return new ArazzoDocument(
        arazzo: '1.0.0',
        info: new Info('deps', null, null, '1'),
        sourceDescriptions: [],
        workflows: $workflows,
        components: new Components([], [], [], []),
        specificationExtensions: [],
    );
}

/**
 * @param  list<array{step: string, callStack: list<string>, workflows: array<string, mixed>}>  $seen
 */
function recordingDependencyStepExecutor(array &$seen, ?string $failStep = null): StepExecutor
{
    return new class($seen, $failStep) extends StepExecutor
    {
        public function __construct(private array &$seen, private ?string $failStep) {}

        public function execute(Step $step, WorkflowContext $context, ArazzoDocument $document): array
        {
            $workflows = $context->getWorkflows();
            $this->seen[] = [
                'step' => $step->stepId,
                'callStack' => $context->getWorkflowCallStack(),
                'workflows' => $workflows,
            ];

            if ($step->stepId === $this->failStep) {
                return [$context->withStepResult($step->stepId, ['outputs' => []]), false];
            }

            return [$context->withStepResult($step->stepId, ['outputs' => ['token' => 't-'.$step->stepId]]), true];
        }
    };
}

function dependencyFixtureExecutor(array &$seen, ?string $failStep = null): WorkflowExecutor
{
    return new WorkflowExecutor(
        recordingDependencyStepExecutor($seen, $failStep),
        new WorkflowEngine(new EvaluationEngine(expression: new ExpressionEngine())),
    );
}

it('runs dependencies first and exposes their outputs inside steps and workflow outputs', function () {
    $seen = [];
    $login = dependencyFixtureWorkflow('login', [], [dependencyFixtureStep('login-step')], [
        'token' => new Expression('$steps.login-step.outputs.token'),
    ]);
    $profile = dependencyFixtureWorkflow('profile', ['login'], [dependencyFixtureStep('profile-step')], [
        'name' => new Expression('$steps.profile-step.outputs.token'),
    ]);
    $orders = dependencyFixtureWorkflow('orders', ['profile', 'login'], [dependencyFixtureStep('orders-step')], [
        'delegated' => new Expression('{$workflows.login.outputs.token}'),
    ]);

    $result = dependencyFixtureExecutor($seen)->execute(
        $orders,
        dependencyFixtureDocument([$orders, $profile, $login]),
        [],
    );

    expect(array_column($seen, 'step'))->toBe(['login-step', 'profile-step', 'orders-step'])
        ->and($seen[1]['workflows']['login']['outputs']['token'] ?? null)->toBe('t-login-step')
        ->and($seen[2]['workflows']['profile']['outputs']['name'] ?? null)->toBe('t-profile-step')
        ->and($result->workflowId)->toBe('orders')
        ->and($result->status)->toBe('succeeded')
        ->and($result->outputs)->toBe(['delegated' => 't-login-step']);
});

it('executes each transitive dependency at most once in a diamond graph', function () {
    $seen = [];
    $base = dependencyFixtureWorkflow('base', [], [dependencyFixtureStep('base-step')]);
    $left = dependencyFixtureWorkflow('left', ['base'], [dependencyFixtureStep('left-step')]);
    $right = dependencyFixtureWorkflow('right', ['base'], [dependencyFixtureStep('right-step')]);
    $target = dependencyFixtureWorkflow('target', ['left', 'right'], [dependencyFixtureStep('target-step')]);

    $result = dependencyFixtureExecutor($seen)->execute(
        $target,
        dependencyFixtureDocument([$target, $left, $right, $base]),
        [],
    );

    expect(array_column($seen, 'step'))->toBe(['base-step', 'left-step', 'right-step', 'target-step'])
        ->and($result->status)->toBe('succeeded');
});

it('does not execute the target when a dependency fails', function () {
    $seen = [];
    $dep = dependencyFixtureWorkflow('dep', [], [dependencyFixtureStep('dep-step')]);
    $target = dependencyFixtureWorkflow('target', ['dep'], [dependencyFixtureStep('target-step')]);

    $result = dependencyFixtureExecutor($seen, 'dep-step')->execute(
        $target,
        dependencyFixtureDocument([$target, $dep]),
        [],
    );

    expect(array_column($seen, 'step'))->toBe(['dep-step'])
        ->and($result->workflowId)->toBe('target')
        ->and($result->status)->toBe('failed');
});

it('fires lifecycle events per workflow with a shared execution id', function () {
    $seen = [];
    $dep = dependencyFixtureWorkflow('dep', [], [dependencyFixtureStep('dep-step')]);
    $target = dependencyFixtureWorkflow('target', ['dep'], [dependencyFixtureStep('target-step')]);

    $events = new SimpleEventDispatcher();
    $started = [];
    $completed = [];
    $events->subscribe(RunStartedEvent::class, function (RunStartedEvent $event) use (&$started): void {
        $started[] = ['id' => $event->executionId, 'workflow' => $event->workflowId];
    });
    $events->subscribe(RunCompletedEvent::class, function (RunCompletedEvent $event) use (&$completed): void {
        $completed[] = ['id' => $event->executionId, 'workflow' => $event->workflowId];
    });

    $executor = new WorkflowExecutor(
        recordingDependencyStepExecutor($seen),
        new WorkflowEngine(new EvaluationEngine(expression: new ExpressionEngine())),
        events: $events,
    );
    $executor->execute($target, dependencyFixtureDocument([$target, $dep]), []);

    expect(array_column($started, 'workflow'))->toBe(['dep', 'target'])
        ->and(array_column($completed, 'workflow'))->toBe(['dep', 'target'])
        ->and(count(array_unique(array_column($started, 'id'))))->toBe(1)
        ->and(array_column($completed, 'id'))->toBe(array_column($started, 'id'));
});

it('keeps the nested invocation stack separate from dependency ids', function () {
    $seen = [];
    $dep = dependencyFixtureWorkflow('dep', [], [dependencyFixtureStep('dep-step')]);
    $target = dependencyFixtureWorkflow('target', ['dep'], [dependencyFixtureStep('target-step')]);

    $result = dependencyFixtureExecutor($seen)->execute(
        $target,
        dependencyFixtureDocument([$target, $dep]),
        [],
    );

    expect($result->workflowCallStack)->toBe(['target'])
        ->and($seen[0]['callStack'])->toBe(['target'])
        ->and($seen[1]['callStack'])->toBe(['target']);
});
