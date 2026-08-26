<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Async;

use Alama\Arazzo\Context\WorkflowContext;
use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Contracts\ExecutionRegistryInterface;
use Alama\Arazzo\Contracts\ExpressionResolverInterface;
use Alama\Arazzo\Contracts\StateStoreInterface;
use Alama\Arazzo\Runner\Events\CorrelationPending;
use Alama\Arazzo\Spec\Enum\StepStatus;
use Alama\Arazzo\Spec\Step;
use Alama\Arazzo\Spec\Workflow;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Side effects for a step whose protocol executor came back suspended:
 * persist the Suspended status, mark the execution started, ledger it, and —
 * for `receive` steps carrying correlation coordinates — announce that the
 * run is waiting on an external message.
 */
final class SuspensionHandler
{
    public function __construct(
        private readonly StateStoreInterface $stateStore,
        private readonly ExecutionRegistryInterface $executionRegistry,
        private readonly EventLedgerInterface $eventLedger,
        private readonly EventDispatcherInterface $events,
        private readonly ExpressionResolverInterface $expressions,
        private readonly int $stateTtlSeconds = 86400,
    ) {}

    public function handle(Step $step, WorkflowContext $context, Workflow $workflow, string $executionId): void
    {
        $newContext = $context->withStepStatus($step->stepId, StepStatus::Suspended);
        $this->stateStore->save($executionId, $newContext->toArray(), $this->stateTtlSeconds);
        $this->executionRegistry->start($executionId, $newContext->getDefinitionId(), $workflow->workflowId);
        $this->eventLedger->append($executionId, 'step.suspended', ['stepId' => $step->stepId]);

        if ($step->action === 'receive' && $step->correlationId !== null && $step->channelPath !== null) {
            $evaluated = $this->expressions->evaluate($step->correlationId, $context, $step->stepId);
            $correlationIdValue = is_scalar($evaluated) ? (string) $evaluated : '';
            $this->events->dispatch(new CorrelationPending(
                $executionId,
                $context->getWorkflowId() ?? '',
                $step->stepId,
                $correlationIdValue,
                $step->channelPath,
                new DateTimeImmutable(),
            ));
        }
    }
}
