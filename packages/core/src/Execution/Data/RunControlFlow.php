<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Data;

use Alama\Arazzo\Async\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Execution\WorkflowEngine;
use Alama\Arazzo\Validator\PreflightValidator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Control-flow capability: the collaborators an orchestrator needs to
 * decide and route execution (engine, queue, events, preflight gate).
 */
final readonly class RunControlFlow
{
    public function __construct(
        public WorkflowEngine $workflowEngine,
        public QueueDriverInterface $queueDriver,
        public ?EventDispatcherInterface $events = null,
        public ?PreflightValidator $preflight = null,
    ) {}
}
