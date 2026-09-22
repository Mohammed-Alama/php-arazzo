<?php

declare(strict_types=1);

use Alama\Arazzo\Contracts\Spec\Enum\StepState;

it('models the workflow state machine states')

    ->expect(StepState::cases())
    ->toBe(
        [
            StepState::Pending,
            StepState::ExecutingRequest,
            StepState::EvaluatingCriteria,
            StepState::AwaitingActorInput,
            StepState::ActorInputReceived,
            StepState::Completed,
            StepState::Failed,
        ],
    );

it('backed by spec-exact string values')

    ->expect(StepState::Pending->value)->toBe('pending')
    ->and(StepState::ExecutingRequest->value)->toBe('executing_request')
    ->and(StepState::EvaluatingCriteria->value)->toBe('evaluating_criteria')
    ->and(StepState::AwaitingActorInput->value)->toBe('awaiting_actor_input')
    ->and(StepState::ActorInputReceived->value)->toBe('actor_input_received')
    ->and(StepState::Completed->value)->toBe('completed')
    ->and(StepState::Failed->value)->toBe('failed');

it('maps retrying to an edge, not a state')

    ->expect(defined('Alama\\Arazzo\\Contracts\\Spec\\Enum\\StepState::Retrying'))->toBeFalse();
