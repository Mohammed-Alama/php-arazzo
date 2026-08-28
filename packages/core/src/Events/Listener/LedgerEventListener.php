<?php

declare(strict_types=1);

namespace Alama\Arazzo\Events\Listener;

use Alama\Arazzo\Events\CorrelationPendingEvent;
use Alama\Arazzo\Events\CorrelationResumedEvent;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\RunStartedEvent;
use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepRetriedEvent;
use Alama\Arazzo\Events\StepStartedEvent;
use Alama\Arazzo\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Throwable;

final class LedgerEventListener
{
    public function __construct(private EventLedgerInterface $ledger) {}

    public static function registerAll(SimpleEventDispatcher $dispatcher, EventLedgerInterface $ledger): void
    {
        $listener = new self($ledger);
        foreach ([
            RunStartedEvent::class, RunCompletedEvent::class, RunFailedEvent::class,
            StepStartedEvent::class, StepExecutedEvent::class, StepRetriedEvent::class, StepFailedEvent::class,
            CorrelationPendingEvent::class, CorrelationResumedEvent::class,
        ] as $eventClass) {
            $dispatcher->subscribe($eventClass, $listener);
        }
    }

    public function __invoke(object $event): void
    {
        [$type, $payload] = match (true) {
            $event instanceof RunStartedEvent => [
                'run.started',
                ['workflowId' => $event->workflowId, 'definitionId' => $event->definitionId, 'inputs' => $event->inputs],
            ],
            $event instanceof RunCompletedEvent => [
                'run.completed',
                ['workflowId' => $event->workflowId, 'outputs' => $event->outputs],
            ],
            $event instanceof RunFailedEvent => [
                'run.failed',
                ['workflowId' => $event->workflowId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof StepStartedEvent => [
                'step.started',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt],
            ],
            $event instanceof StepExecutedEvent => [
                'step.executed',
                ['stepId' => $event->stepId, 'statusCode' => $event->statusCode, 'outputs' => $event->outputs, 'criteriaMet' => $event->criteriaMet],
            ],
            $event instanceof StepRetriedEvent => [
                'step.retried',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt, 'lastError' => $event->lastError !== null ? self::errorPayload($event->lastError) : null],
            ],
            $event instanceof StepFailedEvent => [
                'step.failed',
                ['stepId' => $event->stepId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof CorrelationPendingEvent => [
                'correlation.pending',
                ['stepId' => $event->stepId, 'correlationId' => $event->correlationId, 'channelPath' => $event->channelPath],
            ],
            $event instanceof CorrelationResumedEvent => [
                'correlation.resumed',
                ['stepId' => $event->stepId, 'correlationId' => $event->correlationId],
            ],
            default => [null, []],
        };

        if ($type === null) {
            return;
        }

        /** @var object{executionId: string} $event */
        $this->ledger->append($event->executionId, $type, $payload);
    }

    /** @return array{class: class-string<Throwable>, message: string} */
    private static function errorPayload(Throwable $t): array
    {
        return ['class' => $t::class, 'message' => $t->getMessage()];
    }
}
