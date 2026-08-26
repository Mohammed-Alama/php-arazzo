<?php

declare(strict_types=1);

namespace Alama\Arazzo\Support\Events\Listener;

use Alama\Arazzo\Contracts\EventLedgerInterface;
use Alama\Arazzo\Runner\Events\CorrelationPending;
use Alama\Arazzo\Runner\Events\CorrelationResumed;
use Alama\Arazzo\Runner\Events\RunCompleted;
use Alama\Arazzo\Runner\Events\RunFailed;
use Alama\Arazzo\Runner\Events\RunStarted;
use Alama\Arazzo\Runner\Events\StepExecuted;
use Alama\Arazzo\Runner\Events\StepFailed;
use Alama\Arazzo\Runner\Events\StepRetried;
use Alama\Arazzo\Runner\Events\StepStarted;
use Alama\Arazzo\Support\Events\Dispatcher\SimpleEventDispatcher;
use Throwable;

final class LedgerAppendingListener
{
    public function __construct(private EventLedgerInterface $ledger) {}

    public static function registerAll(SimpleEventDispatcher $dispatcher, EventLedgerInterface $ledger): void
    {
        $listener = new self($ledger);
        foreach ([
            RunStarted::class, RunCompleted::class, RunFailed::class,
            StepStarted::class, StepExecuted::class, StepRetried::class, StepFailed::class,
            CorrelationPending::class, CorrelationResumed::class,
        ] as $eventClass) {
            $dispatcher->subscribe($eventClass, $listener);
        }
    }

    public function __invoke(object $event): void
    {
        [$type, $payload] = match (true) {
            $event instanceof RunStarted => [
                'run.started',
                ['workflowId' => $event->workflowId, 'definitionId' => $event->definitionId, 'inputs' => $event->inputs],
            ],
            $event instanceof RunCompleted => [
                'run.completed',
                ['workflowId' => $event->workflowId, 'outputs' => $event->outputs],
            ],
            $event instanceof RunFailed => [
                'run.failed',
                ['workflowId' => $event->workflowId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof StepStarted => [
                'step.started',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt],
            ],
            $event instanceof StepExecuted => [
                'step.executed',
                ['stepId' => $event->stepId, 'statusCode' => $event->statusCode, 'outputs' => $event->outputs, 'criteriaMet' => $event->criteriaMet],
            ],
            $event instanceof StepRetried => [
                'step.retried',
                ['stepId' => $event->stepId, 'attempt' => $event->attempt, 'lastError' => $event->lastError !== null ? self::errorPayload($event->lastError) : null],
            ],
            $event instanceof StepFailed => [
                'step.failed',
                ['stepId' => $event->stepId, 'error' => self::errorPayload($event->cause)],
            ],
            $event instanceof CorrelationPending => [
                'correlation.pending',
                ['stepId' => $event->stepId, 'correlationId' => $event->correlationId, 'channelPath' => $event->channelPath],
            ],
            $event instanceof CorrelationResumed => [
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
