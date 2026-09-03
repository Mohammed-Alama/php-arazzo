<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Events\Listener;

use Alama\Arazzo\Contracts\Support\Events\Dispatcher\SimpleEventDispatcher;
use Alama\Arazzo\Runner\Events\CorrelationPendingEvent;
use Alama\Arazzo\Runner\Events\CorrelationResumedEvent;
use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\RunCompletedEvent;
use Alama\Arazzo\Runner\Events\RunFailedEvent;
use Alama\Arazzo\Runner\Events\RunStartedEvent;
use Alama\Arazzo\Runner\Events\StepExecutedEvent;
use Alama\Arazzo\Runner\Events\StepFailedEvent;
use Alama\Arazzo\Runner\Events\StepRetriedEvent;
use Alama\Arazzo\Runner\Events\StepStartedEvent;
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
