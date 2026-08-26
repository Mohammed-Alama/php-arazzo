<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution;

use Alama\Arazzo\Contracts\QueueDriverInterface;
use Alama\Arazzo\Validator\PreflightValidator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Control-flow capability: the collaborators an orchestrator needs to
 * decide and route execution (engine, queue, events, preflight gate).
 */
final class RunControlFlow
{
    public function __construct(
        public readonly WorkflowEngine $workflowEngine,
        public readonly QueueDriverInterface $queueDriver,
        public readonly ?EventDispatcherInterface $events = null,
        public readonly ?PreflightValidator $preflight = null,
    ) {}
}
