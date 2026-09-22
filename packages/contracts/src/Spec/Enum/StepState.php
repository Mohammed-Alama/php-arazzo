<?php

declare(strict_types=1);

namespace Alama\Arazzo\Contracts\Spec\Enum;

/**
 * The step lifecycle states of the operation state machine.
 *
 * Retrying is a transition (EvaluatingCriteria returning to Pending),
 * not a state — see the spec state diagram.
 */
enum StepState: string
{
    case Pending = 'pending';
    case ExecutingRequest = 'executing_request';
    case EvaluatingCriteria = 'evaluating_criteria';
    case AwaitingActorInput = 'awaiting_actor_input';
    case ActorInputReceived = 'actor_input_received';
    case Completed = 'completed';
    case Failed = 'failed';
}
