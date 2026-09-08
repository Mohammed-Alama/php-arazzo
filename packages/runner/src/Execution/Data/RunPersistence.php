<?php

declare(strict_types=1);

namespace Alama\Arazzo\Runner\Execution\Data;

use Alama\Arazzo\Runner\Events\Interfaces\EventLedgerInterface;
use Alama\Arazzo\Runner\State\Interfaces\ExecutionRegistryInterface;
use Alama\Arazzo\Runner\State\Interfaces\StateStoreInterface;

/**
 * Durable-execution persistence capability: the collaborators an
 * orchestrator needs to record and resume runs. Grouping them
 * keeps orchestrator interfaces small and makes test fakes cheap
 * (one fake per seam instead of one per constructor parameter).
 *
 * @internal stays out of the advertised contract; not part of the public API surface
 */
final class RunPersistence
{
    public function __construct(
        public readonly StateStoreInterface $stateStore,
        public readonly EventLedgerInterface $eventLedger,
        public readonly ExecutionRegistryInterface $executionRegistry,
    ) {}
}
