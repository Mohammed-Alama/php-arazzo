<?php

declare(strict_types=1);

namespace Alama\Arazzo\Execution\Data;

use Alama\Arazzo\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\State\Interfaces\StateStoreInterface;

/**
 * Durable-execution persistence capability: the collaborators an
 * orchestrator needs to record and resume runs. Grouping them
 * keeps orchestrator interfaces small and makes test fakes cheap
 * (one fake per seam instead of one per constructor parameter).
 */
final class RunPersistence
{
    public function __construct(
        public readonly StateStoreInterface $stateStore,
        public readonly EventLedgerInterface $eventLedger,
        public readonly ExecutionRegistryInterface $executionRegistry,
    ) {}
}
