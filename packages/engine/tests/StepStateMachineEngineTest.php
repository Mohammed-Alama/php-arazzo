<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Action\FailureEndAction;
use Alama\Arazzo\Contracts\Spec\Action\RetryAction;
use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Components;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Info;
use Alama\Arazzo\Contracts\Spec\Interaction;
use Alama\Arazzo\Contracts\Spec\Reusable;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\Spec\StepFlow;
use Alama\Arazzo\Contracts\Spec\StepIo;
use Alama\Arazzo\Contracts\Spec\StepTarget;
use Alama\Arazzo\Contracts\Spec\Workflow;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Engine\Data\Transition;
use Alama\Arazzo\Engine\Enum\StepTransitionType;
use Alama\Arazzo\Engine\StepStateMachineEngine;
use Alama\Arazzo\Engine\WorkflowEngineInterface;

function engineTestDocument(Workflow $workflow): ArazzoDocument
{
    return new ArazzoDocument('1.0.0', new Info('Test', null, null, '1.0.0'), [], [$workflow], new Components([], [], [], []), []);
}

function engineTestWorkflowEngine(): WorkflowEngineInterface
{
    return new class() implements WorkflowEngineInterface
    {
        public function transition(
            ArazzoDocument $document,
            Workflow $workflow,
            Step $step,
            ExecutionState $state,
            bool $criteriaMet,
        ): Transition {
            return new Transition($state, 'succeeded');
        }
    };
}

it('starts from Pending and goes to ExecutingRequest when budget ok', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::ExecutingRequest)
        ->and($transition->type)->toBe(StepTransitionType::Enter)
        ->and($transition->reason)->toBe('guards pass');
});

it('blocks when step budget exceeded in Pending', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = new ExecutionState('exec-1', 'test', 'wf_1', null, [], [], [], [], [], [], 100, 50, ['wf_1'], 32, [], 'running');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::Pending)
        ->and($transition->type)->toBe(StepTransitionType::GuardFailed)
        ->and($transition->reason)->toBe('step budget exceeded');
});

it('bypasses to AwaitingActorInput for interaction steps from Pending', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $target = new StepTarget(interaction: new Interaction());
    $step = new Step('s1', null, $target, new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::Pending, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::AwaitingActorInput)
        ->and($transition->reason)->toBe('interaction step bypass');
});

it('from ExecutingRequest goes to EvaluatingCriteria on response', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->reason)->toBe('response received');
});

it('from ExecutingRequest goes to Failed on suspension', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::ExecutingRequest, $step, $document, $state, false, true);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::Failed)
        ->and($transition->reason)->toBe('transport error / suspended');
});

it('from EvaluatingCriteria goes to Completed when criteria met', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, true, false);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Completed)
        ->and($transition->reason)->toBe('success criteria met');
});

it('from EvaluatingCriteria goes to Failed when criteria not met', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::Failed)
        ->and($transition->reason)->toBe('failure criteria met');
});

it('from EvaluatingCriteria re-routes to AwaitingActorInput for interaction steps', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $target = new StepTarget(interaction: new Interaction());
    $step = new Step('s1', null, $target, new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::EvaluatingCriteria, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->to)->toBe(StepState::AwaitingActorInput)
        ->and($transition->reason)->toBe('actor-in-the-loop re-evaluation');
});

it('from AwaitingActorInput goes to ActorInputReceived', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $target = new StepTarget(interaction: new Interaction());
    $step = new Step('s1', null, $target, new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::AwaitingActorInput, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::AwaitingActorInput)
        ->and($transition->to)->toBe(StepState::ActorInputReceived)
        ->and($transition->reason)->toBe('actor input persisted');
});

it('from ActorInputReceived goes to Completed when criteria met', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $target = new StepTarget(interaction: new Interaction());
    $step = new Step('s1', null, $target, new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::ActorInputReceived, $step, $document, $state, true, false);

    expect($transition->from)->toBe(StepState::ActorInputReceived)
        ->and($transition->to)->toBe(StepState::Completed)
        ->and($transition->reason)->toBe('criteria met after actor input');
});

it('from ActorInputReceived goes to EvaluatingCriteria when criteria not met', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $target = new StepTarget(interaction: new Interaction());
    $step = new Step('s1', null, $target, new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::ActorInputReceived, $step, $document, $state, false, false);

    expect($transition->from)->toBe(StepState::ActorInputReceived)
        ->and($transition->to)->toBe(StepState::EvaluatingCriteria)
        ->and($transition->reason)->toBe('resume evaluation');
});

it('terminal states stay terminal', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $completed = $engine->fire(StepState::Completed, $step, $document, $state, false, false);
    $failed = $engine->fire(StepState::Failed, $step, $document, $state, false, false);

    expect($completed->from)->toBe(StepState::Completed)
        ->and($completed->to)->toBe(StepState::Completed)
        ->and($failed->from)->toBe(StepState::Failed)
        ->and($failed->to)->toBe(StepState::Failed);
});

it('resolves ISO 8601 duration timeout', function (): void {
    $step = new Step('s1', null, new StepTarget(), new StepFlow(timeoutDuration: 'PT1H30M'), new StepIo());
    expect(StepStateMachineEngine::resolveTimeoutSeconds($step))->toBe(5400.0);
});

it('resolves milliseconds timeout', function (): void {
    $step = new Step('s1', null, new StepTarget(), new StepFlow(timeout: 5000), new StepIo());
    expect(StepStateMachineEngine::resolveTimeoutSeconds($step))->toBe(5.0);
});

it('returns null when no timeout specified', function (): void {
    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    expect(StepStateMachineEngine::resolveTimeoutSeconds($step))->toBeNull();
});

it('throws on invalid ISO 8601 duration', function (): void {
    $step = new Step('s1', null, new StepTarget(), new StepFlow(timeoutDuration: 'INVALID'), new StepIo());
    expect(fn () => StepStateMachineEngine::resolveTimeoutSeconds($step))->toThrow(DateMalformedIntervalStringException::class);
});

it('cancels a pending step with the ordered onCancel actions', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $first = new FailureEndAction('stop', []);
    $second = new RetryAction('retry', 1.0, 3, 's1', null, []);
    $step = new Step('s1', null, new StepTarget(), new StepFlow(onCancel: [$first, $second]), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->cancel($step, $document, $state, StepState::Pending);

    expect($transition->from)->toBe(StepState::Pending)
        ->and($transition->to)->toBe(StepState::Cancelled)
        ->and($transition->type)->toBe(StepTransitionType::Cancelled)
        ->and($transition->actions)->toBe([$first, $second]);
});

it('refuses to cancel a completed step', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->cancel($step, $document, $state, StepState::Completed);

    expect($transition->to)->toBe(StepState::Completed)
        ->and($transition->type)->toBe(StepTransitionType::Enter)
        ->and($transition->actions)->toBe([]);
});

it('fails a timed out step with the ordered onTimeout actions', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $first = new FailureEndAction('abort', []);
    $second = new RetryAction('retry', 2.0, 1, null, null, []);
    $step = new Step('s1', null, new StepTarget(), new StepFlow(onTimeout: [$first, $second]), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->timeout($step, $document, $state, StepState::ExecutingRequest);

    expect($transition->from)->toBe(StepState::ExecutingRequest)
        ->and($transition->to)->toBe(StepState::Failed)
        ->and($transition->type)->toBe(StepTransitionType::Timeout)
        ->and($transition->actions)->toBe([$first, $second]);
});

it('refuses to time out a cancelled step', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->timeout($step, $document, $state, StepState::Cancelled);

    expect($transition->to)->toBe(StepState::Cancelled)
        ->and($transition->type)->toBe(StepTransitionType::Enter);
});

it('passes Reusable entries through the cancel action list unresolved', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $reusable = new Reusable('$components.parameters.cancelStep');
    $step = new Step('s1', null, new StepTarget(), new StepFlow(onCancel: [$reusable]), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->cancel($step, $document, $state, StepState::AwaitingActorInput);

    expect($transition->to)->toBe(StepState::Cancelled)
        ->and($transition->actions)->toBe([$reusable]);
});

it('treats Cancelled as terminal in fire()', function (): void {
    $engine = new StepStateMachineEngine(engineTestWorkflowEngine());

    $step = new Step('s1', null, new StepTarget(), new StepFlow(), new StepIo());
    $workflow = new Workflow('wf_1', null, null, null, [], [$step], [], [], [], []);
    $document = engineTestDocument($workflow);
    $state = ExecutionState::start('exec-1', 'test', 'wf_1');

    $transition = $engine->fire(StepState::Cancelled, $step, $document, $state);

    expect($transition->from)->toBe(StepState::Cancelled)
        ->and($transition->to)->toBe(StepState::Cancelled);
});
