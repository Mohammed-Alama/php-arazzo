<?php

declare(strict_types=1);

namespace Alama\LaravelArazzo\Events\Listener;

use Alama\LaravelArazzo\Events\CorrelationPending;
use Alama\LaravelArazzo\Events\CorrelationResumed;
use Alama\LaravelArazzo\Events\Dispatcher\SimpleEventDispatcher;
use Alama\LaravelArazzo\Events\RunCompleted;
use Alama\LaravelArazzo\Events\RunFailed;
use Alama\LaravelArazzo\Events\RunStarted;
use Alama\LaravelArazzo\Events\StepExecuted;
use Alama\LaravelArazzo\Events\StepFailed;
use Alama\LaravelArazzo\Events\StepRetried;
use Alama\LaravelArazzo\Events\StepStarted;
use Alama\LaravelArazzo\Execution\Contracts\EventLedgerInterface;

final class LedgerAppendingListener
{
    public function __construct(private EventLedgerInterface $ledger)
    {
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

    /** @return array{class: class-string<\Throwable>, message: string} */
    private static function errorPayload(\Throwable $t): array
    {
        return ['class' => $t::class, 'message' => $t->getMessage()];
    }
}
