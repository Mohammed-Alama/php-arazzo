<?php

declare(strict_types=1);

namespace Alama\Arazzo\Async;

use Alama\Arazzo\Events\CorrelationPendingEvent;
use Alama\Arazzo\Events\RunCompletedEvent;
use Alama\Arazzo\Events\RunFailedEvent;
use Alama\Arazzo\Events\StepExecutedEvent;
use Alama\Arazzo\Events\StepFailedEvent;
use Alama\Arazzo\Events\StepStartedEvent;
use Alama\Arazzo\Support\Events\Dispatcher\NullEventDispatcher;
use Alama\Arazzo\Validator\Exceptions\SchemaValidationException;
use Alama\Arazzo\Validator\PreflightFailureException;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

/**
 * Typed emission surface for the async adapter's domain events.
 *
 * Every emit site names its intent (step started, run failed with reason…)
 * so the worker reads as orchestration instead of event-constructor soup,
 * and failure-category classification lives in exactly one place.
 */
final class WorkerEvents
{
    private readonly EventDispatcherInterface $events;

    public function __construct(?EventDispatcherInterface $events = null)
    {
        $this->events = $events ?? new NullEventDispatcher();
    }

    public function stepStarted(string $executionId, string $workflowId, string $stepId, int $attempt): void
    {
        $this->events->dispatch(new StepStartedEvent($executionId, $workflowId, $stepId, $attempt, new DateTimeImmutable()));
    }

    /**
     * @param  array<string, mixed>  $outputs
     */
    public function stepExecuted(string $executionId, string $workflowId, string $stepId, int $statusCode, array $outputs, bool $criteriaMet): void
    {
        $this->events->dispatch(new StepExecutedEvent($executionId, $workflowId, $stepId, $statusCode, $outputs, $criteriaMet, new DateTimeImmutable()));
    }

    public function correlationPending(string $executionId, string $workflowId, string $stepId, string $correlationId, string $channelPath): void
    {
        $this->events->dispatch(new CorrelationPendingEvent($executionId, $workflowId, $stepId, $correlationId, $channelPath, new DateTimeImmutable()));
    }

    /** @param array<string, mixed> $outputs */
    public function runCompleted(string $executionId, string $workflowId, array $outputs): void
    {
        $this->events->dispatch(new RunCompletedEvent($executionId, $workflowId, $outputs, new DateTimeImmutable()));
    }

    public function runFailedBecause(string $executionId, string $workflowId, string $reason): void
    {
        $this->events->dispatch(new RunFailedEvent($executionId, $workflowId, new RuntimeException($reason), new DateTimeImmutable()));
    }

    /**
     * Emits the failure PAIR (StepFailedEvent + RunFailedEvent) with one classification
     * so both events always agree on category.
     */
    public function failurePair(string $executionId, string $workflowId, string $stepId, Throwable $cause): void
    {
        $category = match (true) {
            $cause instanceof PreflightFailureException => 'authoring',
            $cause instanceof SchemaValidationException => 'schema',
            default => 'execution',
        };

        $this->events->dispatch(new StepFailedEvent($executionId, $workflowId, $stepId, $cause, new DateTimeImmutable(), $category));
        $this->events->dispatch(new RunFailedEvent($executionId, $workflowId, $cause, new DateTimeImmutable(), $category));
    }
}
