<?php

declare(strict_types=1);

namespace Alama\Arazzo\Engine;

use Alama\Arazzo\Contracts\Spec\ArazzoDocument;
use Alama\Arazzo\Contracts\Spec\Enum\StepState;
use Alama\Arazzo\Contracts\Spec\Step;
use Alama\Arazzo\Contracts\State\ExecutionState;
use Alama\Arazzo\Engine\Data\StepTransition;
use DateInterval;

/**
 * Explicit step-level state machine over StepState.
 *
 * The engine maps (StepState, outcome) pairs to (next StepState, enter-handler).
 * Guards (budget, deps) delegate to the pure WorkflowEngineInterface::transition() for
 * protocol-agnostic decisions. The enter-handlers are side-effect closures
 * invoked after a successful state transition.
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class StepStateMachineEngine
{
    public function __construct(
        private WorkflowEngineInterface $workflowEngine,
    ) {}

    /**
     * Fire a step-level state transition.
     *
     * @param  StepState  $current  The step's current StepState.
     * @param  bool  $criteriaMet  Whether success criteria were met (from executor).
     * @param  bool  $suspended  Whether the executor returned a suspended outcome.
     */
    public function fire(
        StepState $current,
        Step $step,
        ArazzoDocument $document,
        ExecutionState $state,
        bool $criteriaMet = false,
        bool $suspended = false,
    ): StepTransition {
        return match ($current) {
            StepState::Pending => $this->fromPending($step, $document, $state, $criteriaMet),
            StepState::ExecutingRequest => $this->fromExecutingRequest($step, $document, $state, $criteriaMet, $suspended),
            StepState::EvaluatingCriteria => $this->fromEvaluatingCriteria($step, $document, $state, $criteriaMet),
            StepState::AwaitingActorInput => $this->fromAwaitingActorInput($step, $document, $state, $criteriaMet),
            StepState::ActorInputReceived => $this->fromActorInputReceived($step, $document, $state, $criteriaMet),
            StepState::Completed, StepState::Failed => StepTransition::enter($current, $current, 'terminal state'),
        };
    }

    /**
     * Resolve the timeout for a step as seconds (supports ISO 8601 duration strings
     * and integer milliseconds).
     */
    public static function resolveTimeoutSeconds(Step $step): ?float
    {
        if ($step->flow->timeoutDuration !== null) {
            return self::parseIso8601Duration($step->flow->timeoutDuration);
        }

        return $step->flow->timeout !== null ? $step->flow->timeout / 1000.0 : null;
    }

    private function fromPending(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::Pending, StepState::AwaitingActorInput, 'interaction step bypass');
        }

        if ($state->stepsSpent >= $state->maxSteps) {
            return StepTransition::guardFailed(StepState::Pending, 'step budget exceeded');
        }

        return StepTransition::enter(StepState::Pending, StepState::ExecutingRequest, 'guards pass');
    }

    private function fromExecutingRequest(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet, bool $suspended): StepTransition
    {
        if ($suspended) {
            return StepTransition::enter(StepState::ExecutingRequest, StepState::Failed, 'transport error / suspended');
        }

        return StepTransition::enter(StepState::ExecutingRequest, StepState::EvaluatingCriteria, 'response received');
    }

    private function fromEvaluatingCriteria(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Completed, 'success criteria met');
        }

        if ($step->target->interaction !== null) {
            return StepTransition::enter(StepState::EvaluatingCriteria, StepState::AwaitingActorInput, 'actor-in-the-loop re-evaluation');
        }

        return StepTransition::enter(StepState::EvaluatingCriteria, StepState::Failed, 'failure criteria met');
    }

    private function fromAwaitingActorInput(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        return StepTransition::enter(StepState::AwaitingActorInput, StepState::ActorInputReceived, 'actor input persisted');
    }

    private function fromActorInputReceived(Step $step, ArazzoDocument $document, ExecutionState $state, bool $criteriaMet): StepTransition
    {
        if ($criteriaMet) {
            return StepTransition::enter(StepState::ActorInputReceived, StepState::Completed, 'criteria met after actor input');
        }

        return StepTransition::enter(StepState::ActorInputReceived, StepState::EvaluatingCriteria, 'resume evaluation');
    }

    /**
     * Parse an ISO 8601 duration string (e.g. "PT30S", "PT1H30M") into seconds.
     *
     * @throws \DateMalformedIntervalStringException if the string is not a valid ISO 8601 duration.
     */
    private static function parseIso8601Duration(string $duration): float
    {
        $interval = new DateInterval($duration);

        return $interval->h * 3600 + $interval->i * 60 + $interval->s + ($interval->f ?? 0);
    }
}
