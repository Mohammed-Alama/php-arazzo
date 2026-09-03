<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Alama\Arazzo\Contracts\Interfaces\QueueDriverInterface;
use Alama\Arazzo\Document\Validator\PreflightValidator;
use Alama\Arazzo\Runner\Execution\WorkflowEngine;
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
